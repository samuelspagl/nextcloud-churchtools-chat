<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;

/**
 * Diagnostic helper for the D5 spike: dump the ChurchTools chat metadata and the
 * joined Matrix rooms side by side so the exact CT chat -> room mapping can be
 * confirmed against a live tenant.
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
	 * Collect the raw /api/chat payload, the joined Matrix rooms with their relevant
	 * state events, and the candidate aliases derived from the mapping hypothesis.
	 *
	 * @return array{tenantUrl:string,churchToolsChats:list<array<string,mixed>>,matrixRooms:list<array{roomId:string,state:array<string,mixed>}>,suggestedMappings:list<array{chat:array<string,mixed>,candidateAlias:string}>}
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
			$suggestedMappings[] = [
				'chat' => $chat,
				'candidateAlias' => $this->roomMapper->chatRoomAlias($chat['prefix'], $chat['guid'], $server),
			];
		}

		return [
			'tenantUrl' => $tenantUrl,
			'churchToolsChats' => $chats,
			'matrixRooms' => $this->extractRooms($sync),
			'suggestedMappings' => $suggestedMappings,
		];
	}

	/** @param array<string,mixed> $sync @return list<array{roomId:string,state:array<string,mixed>}> */
	private function extractRooms(array $sync): array {
		$joined = $sync['rooms']['join'] ?? [];
		$rooms = [];
		foreach ($joined as $roomId => $room) {
			if (!is_string($roomId) || !is_array($room)) {
				continue;
			}
			$events = is_array($room['state']['events'] ?? null) ? $room['state']['events'] : [];
			$state = [];
			foreach ($events as $event) {
				if (!is_array($event)) {
					continue;
				}
				$type = (string)($event['type'] ?? '');
				if (in_array($type, ['m.room.canonical_alias', 'm.room.name', 'm.room.topic', 'm.room.create', 'm.room.join_rules'], true)
					|| str_starts_with($type, 'ch.')) {
					$state[$type] = $event['content'] ?? null;
				}
			}
			$rooms[] = ['roomId' => $roomId, 'state' => $state];
		}
		return $rooms;
	}
}