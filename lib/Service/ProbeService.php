<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;

/**
 * Administrator diagnostic for the D5 spike: dump the ChurchTools chat metadata
 * and the Matrix rooms side by side so the exact CT chat -> room mapping can be
 * confirmed against a live tenant. ChurchTools /api/chat requires broad tenant
 * administration permission and is never used by normal room loading.
 */
final class ProbeService {
	public function __construct(
		private readonly SecretService $secrets,
		private readonly AppConfigService $appConfig,
		private readonly ChurchToolsClient $churchTools,
		private readonly MatrixClient $matrix,
		private readonly MatrixRoomMapper $roomMapper,
	) {
	}

	/**
	 * Collect the raw /api/chat payload, the joined/invited Matrix rooms with their
	 * relevant state, and the candidate aliases (resolved via the room directory)
	 * derived from the mapping hypothesis.
	 *
	 * @return array{tenantUrl:string,churchToolsChats:list<array<string,mixed>>,matrixRooms:list<array{roomId:string,membership:string,state:array<string,mixed>,stateTypes:array<string,int>}>,suggestedMappings:list<array{chat:array<string,mixed>,candidateAlias:string,resolvedRoomId:string|null}>}
	 */
	public function collect(string $userId): array {
		$tenantUrl = $this->appConfig->requireTenantUrl();
		$ctToken = $this->secrets->getChurchToolsToken($userId);
		$matrixToken = $this->secrets->getMatrixToken($userId);
		if ($ctToken === '' || $matrixToken === '') {
			throw new IntegrationException(
				'not_configured',
				'The user has not connected both ChurchTools and Matrix yet.',
				409,
			);
		}

		$chats = $this->churchTools->getChatsRaw($tenantUrl, $ctToken);
		$sync = $this->matrix->sync($matrixToken);
		$server = $this->appConfig->getMatrixServerName();

		$suggestedMappings = [];
		foreach ($this->churchTools->getChats($tenantUrl, $ctToken) as $chat) {
			$alias = $this->roomMapper->chatRoomAlias($chat['prefix'], $chat['guid'], $server);
			$suggestedMappings[] = [
				'chat' => $chat,
				'candidateAlias' => $alias,
				'resolvedRoomId' => $this->matrix->resolveRoomAlias($matrixToken, $alias),
			];
		}

		return [
			'tenantUrl' => $tenantUrl,
			'churchToolsChats' => $chats,
			'matrixRooms' => $this->extractRooms($sync, $matrixToken),
			'suggestedMappings' => $suggestedMappings,
		];
	}

	/** @param array<string,mixed> $sync @return list<array{roomId:string,membership:string,state:array<string,mixed>,stateTypes:array<string,int>}> */
	private function extractRooms(array $sync, string $matrixToken): array {
		$rooms = [];
		$joined = $sync['rooms']['join'] ?? [];
		if (is_array($joined)) {
			foreach ($joined as $roomId => $_room) {
				if (!is_string($roomId)) {
					continue;
				}
				$events = $this->matrix->roomState($matrixToken, $roomId);
				$rooms[] = ['roomId' => $roomId, 'membership' => 'join', ...$this->extractState($events)];
			}
		}
		$invited = $sync['rooms']['invite'] ?? [];
		if (is_array($invited)) {
			foreach ($invited as $roomId => $room) {
				if (!is_string($roomId) || !is_array($room)) {
					continue;
				}
				$events = is_array($room['invite_state']['events'] ?? null) ? $room['invite_state']['events'] : [];
				$rooms[] = ['roomId' => $roomId, 'membership' => 'invite', ...$this->extractState($events)];
			}
		}
		return $rooms;
	}

	/** @param list<array<string,mixed>> $events @return array{state:array<string,mixed>,stateTypes:array<string,int>} */
	private function extractState(array $events): array {
		$state = [];
		$types = [];
		foreach ($events as $event) {
			if (!is_array($event)) {
				continue;
			}
			$type = (string)($event['type'] ?? '');
			$types[$type] = ($types[$type] ?? 0) + 1;
			if (in_array($type, ['m.room.canonical_alias', 'm.room.name', 'm.room.topic', 'm.room.create', 'm.room.join_rules'], true)
				|| str_starts_with($type, 'ch.')) {
				$state[$type] = $event['content'] ?? null;
			}
		}
		return ['state' => $state, 'stateTypes' => $types];
	}
}
