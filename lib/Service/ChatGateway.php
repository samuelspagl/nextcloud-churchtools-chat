<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;

final class ChatGateway {
	public function __construct(
		private readonly SecretService $secrets,
		private readonly ChurchToolsClient $churchTools,
		private readonly MatrixClient $matrix,
		private readonly MatrixUserId $matrixUserId,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly AppConfigService $appConfig,
	) {
	}

	/** @return array<string,mixed> */
	public function getStatus(string $userId): array {
		$state = $this->secrets->getPublicState($userId);
		return [
			...$state,
			'capabilities' => [
				'rooms' => $state['matrixConnected'],
				'messages' => $state['matrixConnected'],
				'send' => $state['matrixConnected'],
				'directChat' => $state['matrixConnected'],
				'markdown' => true,
				'smartPicker' => true,
			],
		];
	}

	/** @return list<array{id:int,guid:string,matrixUserId:string,displayName:string,imageUrl:string|null,info:string}> */
	public function searchPersons(string $userId, string $query): array {
		$state = $this->secrets->getPublicState($userId);
		$matrixToken = $this->secrets->getMatrixToken($userId);
		$persons = $this->churchTools->searchPersons(
			$this->appConfig->requireTenantUrl(),
			$this->secrets->getChurchToolsToken($userId),
			$query,
		);
		$matrixDirectory = $matrixToken !== ''
			? $this->matrix->searchUserDirectory($matrixToken, $query, 100)
			: [];

		$results = [];
		foreach ($persons as $person) {
			if ($state['personId'] === $person['id']) {
				continue;
			}
			try {
				$matrixUserId = $this->matrixUserId->fromChurchToolsGuid($person['guid']);
				$matrixAvatarUrl = is_array($matrixDirectory[$matrixUserId] ?? null)
					? trim((string)($matrixDirectory[$matrixUserId]['avatar_url'] ?? ''))
					: '';
				$results[] = [
					...$person,
					'matrixUserId' => $matrixUserId,
					'imageUrl' => $matrixAvatarUrl !== '' ? $matrixAvatarUrl : $person['imageUrl'],
				];
			} catch (IntegrationException) {
				continue;
			}
		}
		return $results;
	}

	/** @return array{roomId:string,created:bool,matrixUserId:string,displayName:string,canChat:bool} */
	public function startDirectChat(string $userId, int $personId): array {
		$state = $this->secrets->getPublicState($userId);
		if ($state['personId'] === $personId) {
			throw new IntegrationException('cannot_chat_with_self', 'You cannot start a direct chat with yourself.', 400);
		}

		$person = $this->churchTools->getPerson(
			$this->appConfig->requireTenantUrl(),
			$this->secrets->getChurchToolsToken($userId),
			$personId,
		);
		if (!$person['chatActive']) {
			throw new IntegrationException('person_chat_unavailable', 'ChurchTools Chat is not active for this person.', 409);
		}

		$accessToken = $this->requireMatrixToken($userId);
		$currentMatrixUserId = $this->secrets->getMatrixUserId($userId);
		$targetMatrixUserId = $this->matrixUserId->fromChurchToolsGuid($person['guid']);
		$sync = $this->matrix->sync($accessToken);
		$directRooms = $this->directRooms($sync);
		$existingRoomId = $this->findDirectRoom($sync, $directRooms, $targetMatrixUserId);
		if ($existingRoomId !== null) {
			$targetRooms = $directRooms[$targetMatrixUserId] ?? [];
			if (!in_array($existingRoomId, $targetRooms, true)) {
				$targetRooms[] = $existingRoomId;
				$directRooms[$targetMatrixUserId] = $targetRooms;
				$this->matrix->setDirectRooms($accessToken, $currentMatrixUserId, $directRooms);
			}
			return [
				'roomId' => $existingRoomId,
				'created' => false,
				'matrixUserId' => $targetMatrixUserId,
				'displayName' => $person['displayName'],
				'canChat' => $person['canChat'],
			];
		}

		$roomId = $this->matrix->createDirectRoom($accessToken, $targetMatrixUserId);
		$targetRooms = $directRooms[$targetMatrixUserId] ?? [];
		if (!in_array($roomId, $targetRooms, true)) {
			$targetRooms[] = $roomId;
		}
		$directRooms[$targetMatrixUserId] = $targetRooms;
		$this->matrix->setDirectRooms($accessToken, $currentMatrixUserId, $directRooms);

		return [
			'roomId' => $roomId,
			'created' => true,
			'matrixUserId' => $targetMatrixUserId,
			'displayName' => $person['displayName'],
			'canChat' => $person['canChat'],
		];
	}

	/** @return array{rooms:list<array<string,mixed>>,nextBatch:string|null,churchToolsChats:list<array<string,mixed>>} */
	public function getRooms(string $userId): array {
		$matrixToken = $this->requireMatrixToken($userId);
		$sync = $this->matrix->sync($matrixToken);
		$currentMatrixUserId = $this->secrets->getMatrixUserId($userId);
		$directRooms = $this->resolveDirectRooms($sync, $matrixToken, $currentMatrixUserId);
		return [
			'rooms' => $this->normalizeRooms($sync, $matrixToken, $currentMatrixUserId, $directRooms),
			'nextBatch' => isset($sync['next_batch']) ? (string)$sync['next_batch'] : null,
			'churchToolsChats' => $this->churchTools->getChats(
				$this->appConfig->requireTenantUrl(),
				$this->secrets->getChurchToolsToken($userId),
			),
		];
	}

	/** @return array{events:list<array<string,mixed>>,start:string|null,end:string|null} */
	public function getMessages(string $userId, string $roomId, ?string $from, int $limit): array {
		$this->assertRoomId($roomId);
		$matrixToken = $this->requireMatrixToken($userId);
		$result = $this->matrix->messages($matrixToken, $roomId, $from, $limit);
		$memberEvents = $this->matrix->roomMembers($matrixToken, $roomId);
		if ($memberEvents === []) {
			$memberEvents = $this->syntheticMembersFromSenders(
				is_array($result['chunk'] ?? null) ? $result['chunk'] : [],
			);
		}
		$members = $this->roomMapper->members($memberEvents, $this->profiles($matrixToken, $memberEvents));
		$events = $this->roomMapper->events(is_array($result['chunk'] ?? null) ? $result['chunk'] : [], $members);
		return [
			'events' => array_reverse($events),
			'start' => isset($result['start']) ? (string)$result['start'] : null,
			'end' => isset($result['end']) ? (string)$result['end'] : null,
		];
	}

	/** @return array{events:list<array<string,mixed>>} */
	public function searchMessages(string $userId, string $roomId, string $query, int $limit = 20): array {
		$this->assertRoomId($roomId);
		$query = trim($query);
		if (mb_strlen($query) < 2 || mb_strlen($query) > 200) {
			throw new IntegrationException('invalid_message_search', 'Enter between 2 and 200 characters to search messages.', 400);
		}
		$matrixToken = $this->requireMatrixToken($userId);
		$events = $this->matrix->searchRoomMessages($matrixToken, $roomId, $query, $limit);
		$memberEvents = $this->matrix->roomMembers($matrixToken, $roomId);
		if ($memberEvents === []) {
			$memberEvents = $this->syntheticMembersFromSenders($events);
		}
		$members = $this->roomMapper->members($memberEvents, $this->profiles($matrixToken, $memberEvents));
		return ['events' => $this->roomMapper->events($events, $members)];
	}

	/** @return array{results:list<array{roomId:string,message:array<string,mixed>}>} */
	public function searchConversations(string $userId, string $query, int $limit = 20): array {
		$query = trim($query);
		if (mb_strlen($query) < 2 || mb_strlen($query) > 200) {
			throw new IntegrationException('invalid_conversation_search', 'Enter between 2 and 200 characters to search conversations.', 400);
		}
		$matrixToken = $this->requireMatrixToken($userId);
		$rawResults = $this->matrix->searchMessages($matrixToken, $query, $limit);
		$membersByRoom = [];
		$results = [];
		foreach ($rawResults as $rawResult) {
			$roomId = $rawResult['roomId'];
			if (preg_match('/^![A-Za-z0-9._~+=-]+:[A-Za-z0-9.-]+$/', $roomId) !== 1) {
				continue;
			}
			if (!isset($membersByRoom[$roomId])) {
				$memberEvents = $this->matrix->roomMembers($matrixToken, $roomId);
				if ($memberEvents === []) {
					$memberEvents = $this->syntheticMembersFromSenders([$rawResult['event']]);
				}
				$membersByRoom[$roomId] = $this->roomMapper->members($memberEvents, $this->profiles($matrixToken, $memberEvents));
			}
			$events = $this->roomMapper->events([$rawResult['event']], $membersByRoom[$roomId]);
			if ($events !== []) {
				$results[] = ['roomId' => $roomId, 'message' => $events[0]];
			}
		}
		return ['results' => $results];
	}

	/** @return array{eventId:string,transactionId:string} */
	public function send(string $userId, string $roomId, string $body, ?string $transactionId, ?string $replyTo = null): array {
		$this->assertRoomId($roomId);
		if ($replyTo !== null) {
			$this->assertEventId($replyTo);
		}
		$body = trim($body);
		if ($body === '' || mb_strlen($body) > 10000) {
			throw new IntegrationException('invalid_message', 'Messages must contain between 1 and 10,000 characters.');
		}
		$transactionId = $transactionId !== null && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $transactionId)
			? $transactionId
			: bin2hex(random_bytes(16));
		$result = $this->matrix->sendMessage($this->requireMatrixToken($userId), $roomId, $body, $transactionId, $replyTo);
		return [
			'eventId' => (string)($result['event_id'] ?? ''),
			'transactionId' => $transactionId,
		];
	}

	/** @return array{eventId:string,transactionId:string} */
	public function react(string $userId, string $roomId, string $eventId, string $emoji, ?string $transactionId): array {
		$this->assertRoomId($roomId);
		$this->assertEventId($eventId);
		if (!preg_match('/^.{1,16}$/u', $emoji)) {
			throw new IntegrationException('invalid_reaction', 'The reaction is invalid.');
		}
		$transactionId = $transactionId !== null && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $transactionId)
			? $transactionId
			: bin2hex(random_bytes(16));
		$result = $this->matrix->react($this->requireMatrixToken($userId), $roomId, $eventId, $emoji, $transactionId);
		return ['eventId' => (string)($result['event_id'] ?? ''), 'transactionId' => $transactionId];
	}

	/** @return array{rooms:list<array<string,mixed>>,nextBatch:string|null} */
	public function sync(string $userId, ?string $since): array {
		$matrixToken = $this->requireMatrixToken($userId);
		$currentMatrixUserId = $this->secrets->getMatrixUserId($userId);
		$result = $this->matrix->sync($matrixToken, $since, $since === null ? 0 : 20000);
		$directRooms = $this->resolveDirectRooms($result, $matrixToken, $currentMatrixUserId);
		return [
			'rooms' => $this->normalizeRooms($result, $matrixToken, $currentMatrixUserId, $directRooms),
			'nextBatch' => isset($result['next_batch']) ? (string)$result['next_batch'] : null,
		];
	}

	/** @return array<string,mixed> */
	public function getRoomDetails(string $userId, string $roomId): array {
		$this->assertRoomId($roomId);
		$matrixToken = $this->requireMatrixToken($userId);
		$currentMatrixUserId = $this->secrets->getMatrixUserId($userId);
		$stateEvents = $this->matrix->roomState($matrixToken, $roomId);
		$memberEvents = $this->matrix->roomMembers($matrixToken, $roomId);
		if ($memberEvents === []) {
			$memberEvents = array_values(array_filter(
				$stateEvents,
				static fn (array $event): bool => ($event['type'] ?? '') === 'm.room.member',
			));
		}
		$members = $this->roomMapper->members($memberEvents, $this->profiles($matrixToken, $memberEvents));
		$directRooms = $this->matrix->getDirectRooms($matrixToken, $currentMatrixUserId);
		return $this->roomMapper->details($roomId, $stateEvents, $directRooms, $currentMatrixUserId, $members);
	}

	private function requireMatrixToken(string $userId): string {
		$token = $this->secrets->getMatrixToken($userId);
		if ($token === '') {
			throw new IntegrationException(
				'matrix_not_connected',
				'ChurchTools is connected, but a supported Matrix bootstrap is not available for this account.',
				409,
			);
		}
		return $token;
	}

	private function assertRoomId(string $roomId): void {
		if (!preg_match('/^![A-Za-z0-9._~+=-]+:[A-Za-z0-9.-]+$/', $roomId)) {
			throw new IntegrationException('invalid_room_id', 'The room identifier is invalid.');
		}
	}

	private function assertEventId(string $eventId): void {
		if (!preg_match('/^[$][A-Za-z0-9._~+=-]+(?::[A-Za-z0-9.-]+)?$/', $eventId)) {
			throw new IntegrationException('invalid_event_id', 'The event identifier is invalid.');
		}
	}

	/** @param array<string,mixed> $sync @return array<string,list<string>> */
	private function directRooms(array $sync): array {
		$events = $sync['account_data']['events'] ?? [];
		if (!is_array($events)) {
			return [];
		}
		foreach ($events as $event) {
			if (!is_array($event) || ($event['type'] ?? '') !== 'm.direct' || !is_array($event['content'] ?? null)) {
				continue;
			}
			$directRooms = [];
			foreach ($event['content'] as $matrixUserId => $roomIds) {
				if (!is_string($matrixUserId) || !is_array($roomIds)) {
					continue;
				}
				$directRooms[$matrixUserId] = array_values(array_filter(
					$roomIds,
					static fn (mixed $roomId): bool => is_string($roomId)
						&& strlen($roomId) <= 1024
						&& str_starts_with($roomId, '!')
						&& str_contains($roomId, ':')
						&& preg_match('/[\x00-\x20\x7f]/', $roomId) !== 1,
				));
			}
			return $directRooms;
		}
		return [];
	}

	/** @param array<string,mixed> $sync @param array<string,list<string>> $directRooms */
	private function findDirectRoom(array $sync, array $directRooms, string $targetMatrixUserId): ?string {
		$joined = $sync['rooms']['join'] ?? [];
		if (!is_array($joined)) {
			return null;
		}

		foreach ($directRooms[$targetMatrixUserId] ?? [] as $roomId) {
			if (isset($joined[$roomId])) {
				return $roomId;
			}
		}

		foreach ($joined as $roomId => $room) {
			if (!is_array($room)) {
				continue;
			}
			$heroes = $room['summary']['m.heroes'] ?? [];
			$memberCount = (int)($room['summary']['m.joined_member_count'] ?? 0);
			if (is_array($heroes)
				&& in_array($targetMatrixUserId, $heroes, true)
				&& $memberCount > 0
				&& $memberCount <= 2) {
				return (string)$roomId;
			}
		}
		return null;
	}

	/**
	 * @param array<string,mixed> $sync
	 * @param array<string,list<string>> $directRooms
	 * @return list<array<string,mixed>>
	 */
	private function normalizeRooms(array $sync, string $matrixToken, string $currentMatrixUserId, array $directRooms): array {
		$joined = $sync['rooms']['join'] ?? [];
		if (!is_array($joined)) {
			return [];
		}

		$rooms = [];
		$profileCache = [];
		foreach ($joined as $roomId => $room) {
			if (!is_string($roomId) || !is_array($room)) {
				continue;
			}
			$stateEvents = is_array($room['state']['events'] ?? null) ? $room['state']['events'] : [];
			$timelineEvents = is_array($room['timeline']['events'] ?? null) ? $room['timeline']['events'] : [];
			$memberEvents = $this->matrix->roomMembers($matrixToken, $roomId);
			if ($memberEvents === []) {
				$memberEvents = array_values(array_filter(
					array_merge($stateEvents, $timelineEvents),
					static fn (mixed $event): bool => is_array($event) && ($event['type'] ?? '') === 'm.room.member',
				));
			}
			if ($memberEvents === []) {
				$heroes = is_array($room['summary']['m.heroes'] ?? null) ? $room['summary']['m.heroes'] : [];
				$memberEvents = $this->syntheticMembers($heroes);
			}
			$profiles = $this->profiles($matrixToken, $memberEvents, $profileCache);
			$members = $this->roomMapper->members($memberEvents, $profiles);
			$rooms[] = $this->roomMapper->room($roomId, $room, $directRooms, $currentMatrixUserId, $members);
		}

		usort($rooms, static fn (array $a, array $b): int => ((int)($b['lastMessage']['timestamp'] ?? 0)) <=> ((int)($a['lastMessage']['timestamp'] ?? 0)));
		return $rooms;
	}

	/**
	 * @param array<string,mixed> $sync
	 * @return array<string,list<string>>
	 */
	private function resolveDirectRooms(array $sync, string $matrixToken, string $currentMatrixUserId): array {
		$directRooms = $this->directRooms($sync);
		return $directRooms !== [] ? $directRooms : $this->matrix->getDirectRooms($matrixToken, $currentMatrixUserId);
	}

	/**
	 * @param list<array<string,mixed>> $memberEvents
	 * @param array<string,array{displayname?:string,avatar_url?:string}|null> $cache
	 * @return array<string,array{displayname?:string,avatar_url?:string}>
	 */
	private function profiles(string $matrixToken, array $memberEvents, array &$cache = []): array {
		$profiles = [];
		foreach ($memberEvents as $event) {
			if (($event['type'] ?? '') !== 'm.room.member' || !is_string($event['state_key'] ?? null)) {
				continue;
			}
			$content = is_array($event['content'] ?? null) ? $event['content'] : [];
			if (trim((string)($content['displayname'] ?? '')) !== '' && trim((string)($content['avatar_url'] ?? '')) !== '') {
				continue;
			}
			$matrixUserId = $event['state_key'];
			if (!array_key_exists($matrixUserId, $cache)) {
				$cache[$matrixUserId] = $this->matrix->profile($matrixToken, $matrixUserId);
			}
			if (is_array($cache[$matrixUserId])) {
				$profiles[$matrixUserId] = $cache[$matrixUserId];
			}
		}
		return $profiles;
	}

	/** @param list<array<string,mixed>> $events @return list<array<string,mixed>> */
	private function syntheticMembersFromSenders(array $events): array {
		$userIds = [];
		foreach ($events as $event) {
			if (is_array($event) && is_string($event['sender'] ?? null)) {
				$userIds[] = $event['sender'];
			}
		}
		return $this->syntheticMembers($userIds);
	}

	/** @param list<mixed> $userIds @return list<array<string,mixed>> */
	private function syntheticMembers(array $userIds): array {
		$events = [];
		foreach (array_unique($userIds) as $matrixUserId) {
			if (!is_string($matrixUserId) || $matrixUserId === '') {
				continue;
			}
			$events[] = [
				'type' => 'm.room.member',
				'state_key' => $matrixUserId,
				'content' => ['membership' => 'join'],
			];
		}
		return $events;
	}
}
