<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use JsonException;
use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCP\Http\Client\IClientService;
use Throwable;

final class ChurchToolsClient {
	public function __construct(private readonly IClientService $clientService) {
	}

	/** @return array{id:int,guid:string,displayName:string,canChat:bool,chatActive:bool} */
	public function validateIdentity(string $tenantUrl, string $token): array {
		$payload = $this->request($tenantUrl, $token, '/api/whoami?only_allow_authenticated=true');
		$person = $payload['data'] ?? null;
		if (!is_array($person) || !isset($person['id'], $person['guid'])) {
			throw new IntegrationException('invalid_identity_response', 'ChurchTools returned an incomplete identity response.', 502);
		}

		$displayName = trim((string)($person['firstName'] ?? '') . ' ' . (string)($person['lastName'] ?? ''));
		return [
			'id' => (int)$person['id'],
			'guid' => (string)$person['guid'],
			'displayName' => $displayName !== '' ? $displayName : (string)$person['id'],
			'canChat' => (bool)($person['canChat'] ?? false),
			'chatActive' => (bool)($person['chatActive'] ?? false),
		];
	}

	/** @return list<array<string,mixed>> */
	public function getChatsRaw(string $tenantUrl, string $token): array {
		$payload = $this->request($tenantUrl, $token, '/api/chat');
		$data = $payload['data'] ?? [];
		if (!is_array($data)) {
			throw new IntegrationException('invalid_chat_response', 'ChurchTools returned an invalid chat list.', 502);
		}
		return array_is_list($data) ? $data : [$data];
	}

	/** @return list<array{creator:int|null,domainId:int,guid:string,prefix:string,roomname:string|null,status:string}> */
	public function getChats(string $tenantUrl, string $token): array {
		$chats = [];
		foreach ($this->getChatsRaw($tenantUrl, $token) as $item) {
			if (!is_array($item)) {
				continue;
			}
			$guid = (string)($item['guid'] ?? '');
			$prefix = (string)($item['prefix'] ?? '');
			$domainId = (int)($item['domainId'] ?? 0);
			if ($guid === '' || $prefix === '' || $domainId <= 0) {
				continue;
			}
			$roomname = $item['roomname'] ?? null;
			$chats[] = [
				'creator' => isset($item['creator']) && is_int($item['creator']) ? $item['creator'] : null,
				'domainId' => $domainId,
				'guid' => $guid,
				'prefix' => $prefix,
				'roomname' => is_string($roomname) && $roomname !== '' ? $roomname : null,
				'status' => (string)($item['status'] ?? 'NOT_STARTED'),
			];
		}
		return $chats;
	}

	/** @return list<array{id:int,guid:string,displayName:string,imageUrl:string|null,info:string}> */
	public function searchPersons(string $tenantUrl, string $token, string $query): array {
		$query = trim($query);
		if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
			throw new IntegrationException('invalid_person_query', 'Enter between 2 and 100 characters to search for a person.', 400);
		}

		$payload = $this->request($tenantUrl, $token, '/api/search?' . http_build_query([
			'query' => $query,
			'domain_types[]' => 'person',
		]));
		$data = $payload['data'] ?? [];
		if (!is_array($data)) {
			throw new IntegrationException('invalid_person_search_response', 'ChurchTools returned an invalid person search response.', 502);
		}

		$persons = [];
		foreach ($data as $item) {
			if (!is_array($item) || ($item['domainType'] ?? '') !== 'person') {
				continue;
			}
			$attributes = is_array($item['domainAttributes'] ?? null) ? $item['domainAttributes'] : [];
			$idValue = (string)($item['domainIdentifier'] ?? '');
			if (!ctype_digit($idValue)
				&& preg_match('~/(?:api/)?persons/([0-9]+)(?:$|[/?])~', (string)($item['apiUrl'] ?? ''), $matches)) {
				$idValue = $matches[1];
			}
			$guid = (string)($attributes['guid'] ?? '');
			if (!ctype_digit($idValue)
				|| (int)$idValue <= 0
				|| $guid === ''
				|| (bool)($attributes['isArchived'] ?? false)
				|| ($attributes['dateOfDeath'] ?? null) !== null) {
				continue;
			}

			$displayName = trim((string)($attributes['firstName'] ?? '') . ' ' . (string)($attributes['lastName'] ?? ''));
			if ($displayName === '') {
				$displayName = trim((string)($item['title'] ?? ''));
			}
			$infos = is_array($item['infos'] ?? null) ? array_filter($item['infos'], 'is_string') : [];
			$persons[] = [
				'id' => (int)$idValue,
				'guid' => $guid,
				'displayName' => $displayName !== '' ? $displayName : 'Person ' . $idValue,
				'imageUrl' => is_string($item['imageUrl'] ?? null) ? $item['imageUrl'] : null,
				'info' => mb_substr(implode(' · ', $infos), 0, 200),
			];
			if (count($persons) >= 25) {
				break;
			}
		}

		return $persons;
	}

	/** @return array{id:int,guid:string,displayName:string,canChat:bool,chatActive:bool} */
	public function getPerson(string $tenantUrl, string $token, int $personId): array {
		if ($personId <= 0) {
			throw new IntegrationException('invalid_person', 'The selected ChurchTools person is invalid.', 400);
		}

		$payload = $this->request($tenantUrl, $token, '/api/persons?' . http_build_query([
			'ids[]' => $personId,
			'limit' => 1,
		]));
		$data = $payload['data'] ?? [];
		$person = is_array($data) && array_is_list($data) ? ($data[0] ?? null) : null;
		if (!is_array($person) || (int)($person['id'] ?? 0) !== $personId || !isset($person['guid'])) {
			throw new IntegrationException('person_not_found', 'The selected ChurchTools person is unavailable.', 404);
		}

		$displayName = trim((string)($person['firstName'] ?? '') . ' ' . (string)($person['lastName'] ?? ''));
		return [
			'id' => $personId,
			'guid' => (string)$person['guid'],
			'displayName' => $displayName !== '' ? $displayName : (string)$personId,
			'canChat' => (bool)($person['canChat'] ?? false),
			'chatActive' => (bool)($person['chatActive'] ?? false),
		];
	}

	/** @return list<array{id:int,name:string,frontendUrl:string|null}> */
	public function searchGroups(string $tenantUrl, string $token, string $query): array {
		$query = trim($query);
		if ($query === '' || mb_strlen($query) > 200) {
			throw new IntegrationException('invalid_group_query', 'The group name is invalid.', 400);
		}

		$payload = $this->request($tenantUrl, $token, '/api/search?' . http_build_query([
			'query' => $query,
			'domain_types' => ['group'],
		]));
		$data = $payload['data'] ?? [];
		if (!is_array($data)) {
			throw new IntegrationException('invalid_group_search_response', 'ChurchTools returned an invalid group search response.', 502);
		}

		$groups = [];
		foreach ($data as $item) {
			if (!is_array($item) || ($item['domainType'] ?? '') !== 'group') {
				continue;
			}
			$attributes = is_array($item['domainAttributes'] ?? null) ? $item['domainAttributes'] : [];
			$idValue = (string)($item['domainIdentifier'] ?? '');
			if (!ctype_digit($idValue)
				&& preg_match('~/(?:api/)?groups/([0-9]+)(?:$|[/?])~', (string)($item['apiUrl'] ?? ''), $matches)) {
				$idValue = $matches[1];
			}
			$name = trim((string)($attributes['name'] ?? $item['title'] ?? ''));
			if (!ctype_digit($idValue) || (int)$idValue <= 0 || $name === '') {
				continue;
			}
			$frontendUrl = $item['frontendUrl'] ?? null;
			$groups[] = [
				'id' => (int)$idValue,
				'name' => $name,
				'frontendUrl' => is_string($frontendUrl) && $frontendUrl !== '' ? $frontendUrl : null,
			];
		}

		return $groups;
	}

	/** @return array<string,mixed> */
	public function getGroup(string $tenantUrl, string $token, int $groupId): array {
		if ($groupId <= 0) {
			throw new IntegrationException('invalid_group', 'The selected ChurchTools group is invalid.', 400);
		}
		$payload = $this->request($tenantUrl, $token, '/api/groups/' . $groupId . '?' . http_build_query([
			'include' => ['roles'],
		]));
		$data = $payload['data'] ?? [];
		$group = is_array($data) ? $data : null;
		if (!is_array($group) || (int)($group['id'] ?? 0) !== $groupId) {
			throw new IntegrationException('group_not_found', 'The selected ChurchTools group is unavailable.', 404);
		}
		return $group;
	}

	/** @return list<array<string,mixed>> */
	public function getGroupMembers(string $tenantUrl, string $token, int $groupId): array {
		if ($groupId <= 0) {
			throw new IntegrationException('invalid_group', 'The selected ChurchTools group is invalid.', 400);
		}
		$members = [];
		$page = 1;
		do {
			$payload = $this->request($tenantUrl, $token, '/api/groups/' . $groupId . '/members?' . http_build_query([
				'limit' => 100,
				'page' => $page,
			]));
			$data = $payload['data'] ?? [];
			if (!is_array($data)) {
				throw new IntegrationException('invalid_group_members_response', 'ChurchTools returned invalid group members.', 502);
			}
			array_push($members, ...array_values(array_filter($data, 'is_array')));
			$pagination = is_array($payload['meta']['pagination'] ?? null) ? $payload['meta']['pagination'] : [];
			$lastPage = max(1, (int)($pagination['lastPage'] ?? 1));
			$page++;
		} while ($page <= $lastPage);

		return $members;
	}

	/** @return list<array<string,mixed>> */
	public function getGroupTypes(string $tenantUrl, string $token): array {
		return $this->getList($tenantUrl, $token, '/api/group/grouptypes', 'invalid_group_types_response');
	}

	/** @return array<string,mixed> */
	public function getGroupCategory(string $tenantUrl, string $token, int $categoryId): array {
		if ($categoryId <= 0) {
			throw new IntegrationException('invalid_group_category', 'The selected ChurchTools group category is invalid.', 400);
		}
		$payload = $this->request($tenantUrl, $token, '/api/group/groupcategories/' . $categoryId);
		$data = $payload['data'] ?? null;
		if (!is_array($data) || (int)($data['id'] ?? 0) !== $categoryId) {
			throw new IntegrationException('invalid_group_category_response', 'ChurchTools returned invalid group category data.', 502);
		}
		return $data;
	}

	/** @return list<array<string,mixed>> */
	private function getList(string $tenantUrl, string $token, string $path, string $errorCode): array {
		$payload = $this->request($tenantUrl, $token, $path);
		$data = $payload['data'] ?? [];
		if (!is_array($data)) {
			throw new IntegrationException($errorCode, 'ChurchTools returned invalid group master data.', 502);
		}
		return array_values(array_filter($data, 'is_array'));
	}

	/** @return array<string,mixed> */
	private function request(string $tenantUrl, string $token, string $path): array {
		if ($token === '') {
			throw new IntegrationException('not_configured', 'Connect your ChurchTools account in Personal settings.', 409);
		}

		try {
			$response = $this->clientService->newClient()->get($tenantUrl . $path, [
				'headers' => [
					'Accept' => 'application/json',
					'Authorization' => 'Login ' . $token,
				],
				'connect_timeout' => 5,
				'timeout' => 15,
				'http_errors' => false,
			]);
			$status = $response->getStatusCode();
			if ($status === 401) {
				throw new IntegrationException('invalid_token', 'The ChurchTools access token is invalid.', 401);
			}
			if ($status === 403) {
				throw new IntegrationException('churchtools_forbidden', 'ChurchTools denied access to this resource.', 403);
			}
			if ($status < 200 || $status >= 300) {
				throw new IntegrationException('churchtools_unavailable', 'ChurchTools could not complete the request.', 502);
			}

			$data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($data)) {
				throw new IntegrationException('invalid_churchtools_response', 'ChurchTools returned an invalid response.', 502);
			}
			return $data;
		} catch (IntegrationException $e) {
			throw $e;
		} catch (JsonException) {
			throw new IntegrationException('invalid_churchtools_response', 'ChurchTools returned malformed JSON.', 502);
		} catch (Throwable) {
			throw new IntegrationException('churchtools_unavailable', 'ChurchTools is currently unavailable.', 502);
		}
	}
}
