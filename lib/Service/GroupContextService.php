<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCP\Teams\ITeamManager;
use OCP\Teams\Team;
use OCP\Teams\TeamResource;
use Throwable;

final class GroupContextService {
	public function __construct(
		private readonly RoomDetailsProvider $gateway,
		private readonly ChurchToolsClient $churchTools,
		private readonly SecretService $secrets,
		private readonly AppConfigService $appConfig,
		private readonly ITeamManager $teamManager,
		private readonly MatrixUserId $matrixUserId,
	) {
	}

	/** @return array<string,mixed> */
	public function getForRoom(string $userId, string $roomId): array {
		$room = $this->gateway->getRoomDetails($userId, $roomId);
		if (($room['kind'] ?? '') !== 'group') {
			return $this->withoutMatch('not_applicable');
		}

		$roomName = trim((string)($room['name'] ?? ''));
		if ($roomName === '') {
			return $this->withoutMatch('none');
		}
		$tenantUrl = $this->appConfig->requireTenantUrl();
		$token = $this->secrets->getChurchToolsToken($userId);
		$matches = array_values(array_filter(
			$this->churchTools->searchGroups($tenantUrl, $token, $roomName),
			fn (array $group): bool => $this->normalizeName($group['name']) === $this->normalizeName($roomName),
		));
		if ($matches === []) {
			return $this->withoutMatch('none');
		}
		if (count($matches) !== 1) {
			return $this->withoutMatch('ambiguous');
		}

		$match = $matches[0];
		$group = $this->churchTools->getGroup($tenantUrl, $token, $match['id']);
		$members = $this->optionalList(fn (): array => $this->churchTools->getGroupMembers($tenantUrl, $token, $match['id']));
		$types = $this->optionalList(fn (): array => $this->churchTools->getGroupTypes($tenantUrl, $token));
		$categoryId = $this->masterDataId($group, 'groupCategoryId');
		$category = $categoryId > 0
			? $this->optionalItem(fn (): array => $this->churchTools->getGroupCategory($tenantUrl, $token, $categoryId))
			: null;
		$name = trim((string)($group['name'] ?? $match['name']));
		$memberCount = count(array_filter($members, fn (array $member): bool => $this->isActiveMember($member)));

		return [
			'matchStatus' => 'matched',
			'group' => [
				'id' => $match['id'],
				'name' => $name !== '' ? $name : $match['name'],
				'visibility' => $this->visibility($group),
				'groupType' => $this->masterDataName($group, $types, 'groupTypeId'),
				'category' => $this->itemName($category),
				'description' => $this->description($group),
				'frontendUrl' => $this->validatedFrontendUrl($tenantUrl, $match['frontendUrl']),
				'leadership' => $this->leadership($group, $members, is_array($room['members'] ?? null) ? $room['members'] : []),
				'memberCount' => $memberCount,
			],
			'nextcloud' => $this->nextcloudContext($userId, $name !== '' ? $name : $match['name']),
		];
	}

	/** @return array{matchStatus:string,group:null,nextcloud:array{status:string,teams:list<mixed>}} */
	private function withoutMatch(string $status): array {
		return [
			'matchStatus' => $status,
			'group' => null,
			'nextcloud' => ['status' => 'unavailable', 'teams' => []],
		];
	}

	private function normalizeName(string $name): string {
		$collapsed = preg_replace('/\s+/u', ' ', trim($name));
		return mb_strtolower($collapsed ?? trim($name));
	}

	/** @param callable():array $operation @return list<array<string,mixed>> */
	private function optionalList(callable $operation): array {
		try {
			return $operation();
		} catch (IntegrationException $e) {
			if (in_array($e->getHttpStatus(), [401, 409], true)) {
				throw $e;
			}
			return [];
		}
	}

	/** @param callable():array<string,mixed> $operation @return array<string,mixed>|null */
	private function optionalItem(callable $operation): ?array {
		try {
			return $operation();
		} catch (IntegrationException $e) {
			if (in_array($e->getHttpStatus(), [401, 409], true)) {
				throw $e;
			}
			return null;
		}
	}

	/** @param array<string,mixed> $group */
	private function visibility(array $group): ?string {
		$information = is_array($group['information'] ?? null) ? $group['information'] : [];
		$settings = is_array($group['settings'] ?? null) ? $group['settings'] : [];
		$value = $group['visibility'] ?? $information['visibility'] ?? $settings['visibility'] ?? null;
		if (is_string($value) && $value !== '') {
			$normalized = strtolower($value);
			return match ($normalized) {
				'public', 'internal', 'restricted', 'hidden' => $normalized,
				'intern' => 'internal',
				default => 'unknown',
			};
		}
		$isHidden = $group['isHidden'] ?? $information['isHidden'] ?? $settings['isHidden'] ?? null;
		$isPublic = $group['isPublic'] ?? $information['isPublic'] ?? $settings['isPublic'] ?? null;
		if ($isHidden === true) return 'hidden';
		if ($isPublic === true) return 'public';
		return null;
	}

	/** @param array<string,mixed> $group */
	private function description(array $group): ?string {
		$information = is_array($group['information'] ?? null) ? $group['information'] : [];
		foreach ([$group['description'] ?? null, $group['note'] ?? null, $information['description'] ?? null, $information['note'] ?? null] as $value) {
			if (is_string($value) && trim($value) !== '') return trim($value);
		}
		return null;
	}

	/** @param array<string,mixed> $group @param list<array<string,mixed>> $masterData */
	private function masterDataName(array $group, array $masterData, string $idKey): ?string {
		$information = is_array($group['information'] ?? null) ? $group['information'] : [];
		$embeddedKey = $idKey === 'groupTypeId' ? 'groupType' : 'groupCategory';
		$embedded = $group[$embeddedKey] ?? $information[$embeddedKey] ?? null;
		if (is_array($embedded)) return $this->itemName($embedded);
		$id = $this->masterDataId($group, $idKey);
		foreach ($masterData as $item) {
			if ((int)($item['id'] ?? 0) === $id) return $this->itemName($item);
		}
		return null;
	}

	/** @param array<string,mixed> $group */
	private function masterDataId(array $group, string $idKey): int {
		$information = is_array($group['information'] ?? null) ? $group['information'] : [];
		return (int)($group[$idKey] ?? $information[$idKey] ?? 0);
	}

	/** @param array<string,mixed>|null $item */
	private function itemName(?array $item): ?string {
		if ($item === null) return null;
		$name = $item['nameTranslated'] ?? $item['name'] ?? null;
		return is_string($name) && trim($name) !== '' ? trim($name) : null;
	}

	private function validatedFrontendUrl(string $tenantUrl, ?string $frontendUrl): ?string {
		if ($frontendUrl === null) return null;
		$url = parse_url($frontendUrl);
		$tenant = parse_url($tenantUrl);
		if (!is_array($url) || !is_array($tenant)
			|| ($url['scheme'] ?? '') !== 'https'
			|| strtolower((string)($url['host'] ?? '')) !== strtolower((string)($tenant['host'] ?? ''))) {
			return null;
		}
		return $frontendUrl;
	}

	/** @param array<string,mixed> $group @param list<array<string,mixed>> $members @param list<array<string,mixed>> $roomMembers @return list<array<string,mixed>> */
	private function leadership(array $group, array $members, array $roomMembers): array {
		$avatarByMatrixId = [];
		foreach ($roomMembers as $roomMember) {
			if (!is_array($roomMember) || !isset($roomMember['id'])) continue;
			$avatarByMatrixId[(string)$roomMember['id']] = is_string($roomMember['avatarUrl'] ?? null) ? $roomMember['avatarUrl'] : null;
		}

		$roles = is_array($group['roles'] ?? null) ? $group['roles'] : [];
		$leaders = [];
		foreach ($roles as $role) {
			if (!is_array($role)
				|| ($role['isActive'] ?? true) === false
				|| (($role['isLeader'] ?? false) !== true && ($role['type'] ?? '') !== 'leader')) continue;
			$roleId = (int)($role['id'] ?? $role['groupTypeRoleId'] ?? 0);
			$typeRoleId = (int)($role['groupTypeRoleId'] ?? $roleId);
			if ($roleId <= 0 && $typeRoleId <= 0) continue;
			$key = (string)($typeRoleId > 0 ? $typeRoleId : $roleId);
			$leaders[$key] = [
				'roleId' => $typeRoleId > 0 ? $typeRoleId : $roleId,
				'roleName' => trim((string)($role['name'] ?? '')) ?: 'Leadership',
				'members' => [],
				'acceptedIds' => array_values(array_unique([$roleId, $typeRoleId])),
			];
		}

		foreach ($members as $member) {
			if (!$this->isActiveMember($member)) continue;
			$roleId = (int)($member['groupTypeRoleId'] ?? $member['groupMemberRoleId'] ?? $member['roleId'] ?? 0);
			foreach ($leaders as &$leadership) {
				if (!in_array($roleId, $leadership['acceptedIds'], true)) continue;
				$person = is_array($member['person'] ?? null) ? $member['person'] : $member;
				$domainIdentifier = (string)($person['domainIdentifier'] ?? '');
				$personId = ctype_digit($domainIdentifier) ? (int)$domainIdentifier : (int)($person['id'] ?? $member['personId'] ?? 0);
				$name = trim((string)($person['displayName'] ?? ''));
				if ($name === '') $name = trim((string)($person['title'] ?? ''));
				$attributes = is_array($person['domainAttributes'] ?? null) ? $person['domainAttributes'] : [];
				if ($name === '') $name = trim((string)($attributes['firstName'] ?? '') . ' ' . (string)($attributes['lastName'] ?? ''));
				if ($name === '') $name = trim((string)($person['firstName'] ?? '') . ' ' . (string)($person['lastName'] ?? ''));
				if ($name === '') $name = trim((string)($member['personName'] ?? ''));
				if ($personId <= 0 || $name === '') continue;
				$guid = (string)($person['guid'] ?? $attributes['guid'] ?? '');
				$avatarUrl = null;
				if ($guid !== '') {
					try {
						$avatarUrl = $avatarByMatrixId[$this->matrixUserId->fromChurchToolsGuid($guid)] ?? null;
					} catch (IntegrationException) {
						$avatarUrl = null;
					}
				}
				$leadership['members'][] = ['personId' => $personId, 'displayName' => $name, 'avatarUrl' => $avatarUrl];
			}
			unset($leadership);
		}

		return array_values(array_map(static function (array $role): array {
			unset($role['acceptedIds']);
			return $role;
		}, $leaders));
	}

	/** @param array<string,mixed> $member */
	private function isActiveMember(array $member): bool {
		if (($member['isActive'] ?? true) === false) return false;
		$status = $member['groupMemberStatus'] ?? $member['memberStatus'] ?? $member['status'] ?? null;
		if (is_array($status)) $status = $status['name'] ?? $status['status'] ?? null;
		return !is_string($status) || $status === '' || strtolower($status) === 'active';
	}

	/** @return array{status:string,teams:list<array<string,mixed>>} */
	private function nextcloudContext(string $userId, string $groupName): array {
		try {
			// Nextcloud 34 exposes this capability check; Nextcloud 33 does not yet.
			if (method_exists($this->teamManager, 'hasTeamSupport') && !$this->teamManager->hasTeamSupport()) {
				return ['status' => 'unavailable', 'teams' => []];
			}
			$teams = array_values(array_filter(
				$this->teamManager->getTeamsForUser($userId),
				fn (Team $team): bool => $this->normalizeName($team->getDisplayName()) === $this->normalizeName($groupName),
			));
			$result = [];
			foreach ($teams as $team) {
				$resources = [];
				foreach ($this->teamManager->getSharedWith($team->getId(), $userId) as $resource) {
					if (!$resource instanceof TeamResource) continue;
					$provider = $resource->getProvider();
					$providerKey = strtolower($provider->getId() . ' ' . $provider->getName());
					$kind = str_contains($providerKey, 'deck') ? 'deck-board' : (str_contains($providerKey, 'file') ? 'folder' : null);
					if ($kind === null) continue;
					$resources[] = [
						'id' => $resource->getId(),
						'kind' => $kind,
						'label' => $resource->getLabel(),
						'url' => $resource->getUrl(),
					];
				}
				$result[] = [
					'id' => $team->getId(),
					'name' => $team->getDisplayName(),
					'url' => $team->getLink(),
					'resources' => $resources,
				];
			}
			return ['status' => $result === [] ? 'none' : 'matched', 'teams' => $result];
		} catch (Throwable) {
			return ['status' => 'error', 'teams' => []];
		}
	}
}
