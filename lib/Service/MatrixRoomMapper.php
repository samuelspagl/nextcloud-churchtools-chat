<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

final class MatrixRoomMapper {
	/**
	 * @param list<array<string,mixed>> $events
	 * @param array<string,array{displayname?:string,avatar_url?:string}> $profiles
	 * @return list<array{id:string,displayName:string,avatarUrl:string|null,membership:string}>
	 */
	public function members(array $events, array $profiles = []): array {
		$latest = [];
		foreach ($events as $event) {
			if (($event['type'] ?? '') !== 'm.room.member' || !is_string($event['state_key'] ?? null)) {
				continue;
			}
			$latest[$event['state_key']] = $event;
		}

		$members = [];
		foreach ($latest as $matrixUserId => $event) {
			$content = is_array($event['content'] ?? null) ? $event['content'] : [];
			$membership = (string)($content['membership'] ?? 'leave');
			if (!in_array($membership, ['join', 'invite'], true)) {
				continue;
			}
			$profile = $profiles[$matrixUserId] ?? [];
			$displayName = trim((string)($content['displayname'] ?? ''));
			if ($displayName === '') {
				$displayName = trim((string)($profile['displayname'] ?? ''));
			}
			$avatarUrl = trim((string)($content['avatar_url'] ?? ''));
			if ($avatarUrl === '') {
				$avatarUrl = trim((string)($profile['avatar_url'] ?? ''));
			}
			$members[] = [
				'id' => $matrixUserId,
				'displayName' => $displayName !== '' ? $displayName : $this->fallbackUserName($matrixUserId),
				'avatarUrl' => $avatarUrl !== '' ? $avatarUrl : null,
				'membership' => $membership,
			];
		}

		usort($members, static fn (array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));
		return $members;
	}

	/**
	 * @param array<string,mixed> $room
	 * @param array<string,list<string>> $directRooms
	 * @param list<array{id:string,displayName:string,avatarUrl:string|null,membership:string}> $members
	 * @return array<string,mixed>
	 */
	public function room(string $roomId, array $room, array $directRooms, string $currentUserId, array $members): array {
		$stateEvents = is_array($room['state']['events'] ?? null) ? $room['state']['events'] : [];
		$timelineEvents = is_array($room['timeline']['events'] ?? null) ? $room['timeline']['events'] : [];
		$allEvents = array_merge($stateEvents, $timelineEvents);
		$directTarget = $this->directTarget($roomId, $directRooms, $currentUserId, $members);
		$kind = $directTarget !== null ? 'direct' : 'group';
		$memberMap = [];
		foreach ($members as $member) {
			$memberMap[$member['id']] = $member;
		}

		$name = null;
		if ($directTarget !== null) {
			$name = $memberMap[$directTarget]['displayName'] ?? $this->fallbackUserName($directTarget);
		}
		if ($name === null || trim($name) === '') {
			$name = $this->stateString($allEvents, 'm.room.name', 'name')
				?? $this->stateString($allEvents, 'm.room.canonical_alias', 'alias')
				?? $this->memberSummary($members, $currentUserId)
				?? 'Conversation';
		}

		$avatarUrl = $directTarget !== null ? ($memberMap[$directTarget]['avatarUrl'] ?? null) : null;
		$avatarUrl ??= $this->stateString($allEvents, 'm.room.avatar', 'url');
		$messages = $this->events($timelineEvents, $members, $currentUserId);
		$lastMessage = $messages === [] ? null : $messages[array_key_last($messages)];
		$joinedCount = count(array_filter($members, static fn (array $member): bool => $member['membership'] === 'join'));

		$timeline = is_array($room['timeline'] ?? null) ? $room['timeline'] : [];
		$limited = ($timeline['limited'] ?? false) === true;
		$prevBatch = isset($timeline['prev_batch']) ? (string)$timeline['prev_batch'] : null;

		$accountData = is_array($room['account_data']['events'] ?? null) ? $room['account_data']['events'] : [];
		$fullyReadEventId = null;
		foreach ($accountData as $accountEvent) {
			if (($accountEvent['type'] ?? '') === 'm.fully_read' && is_array($accountEvent['content'] ?? null)) {
				$fullyReadEventId = (string)($accountEvent['content']['event_id'] ?? '');
			}
		}

		$ephemeral = $this->ephemeral($room, $memberMap, $currentUserId);

		return [
			'id' => $roomId,
			'name' => $name,
			'avatarUrl' => $avatarUrl,
			'encrypted' => $this->stateString($allEvents, 'm.room.encryption', 'algorithm') !== null,
			'kind' => $kind,
			'memberCount' => (int)($room['summary']['m.joined_member_count'] ?? $joinedCount),
			'unreadCount' => (int)($room['unread_notifications']['notification_count'] ?? 0),
			'limited' => $limited,
			'prevBatch' => $prevBatch,
			'fullyReadEventId' => $fullyReadEventId,
			'typingUsers' => $ephemeral['typingUsers'],
			'readReceipts' => $ephemeral['readReceipts'],
			'lastMessage' => $lastMessage,
			'events' => $messages,
		];
	}

	/**
	 * Read the ephemeral room events from a sync response: typing users and public
	 * read receipts (m.read), excluding the current user's own state.
	 *
	 * @param array<string,mixed> $room
	 * @param array<string,array{id:string,displayName:string,avatarUrl:string|null,membership:string}> $memberMap
	 * @return array{typingUsers:list<array{id:string,displayName:string}>,readReceipts:array<string,string>}
	 */
	private function ephemeral(array $room, array $memberMap, string $currentUserId): array {
		$typing = [];
		$readReceipts = [];
		$events = is_array($room['ephemeral']['events'] ?? null) ? $room['ephemeral']['events'] : [];
		foreach ($events as $event) {
			if (!is_array($event) || !is_array($event['content'] ?? null)) {
				continue;
			}
			$content = $event['content'];
			$type = $event['type'] ?? '';
			if ($type === 'm.typing') {
				$userIds = is_array($content['user_ids'] ?? null) ? $content['user_ids'] : [];
				foreach ($userIds as $matrixUserId) {
					if (!is_string($matrixUserId) || $matrixUserId === '' || $matrixUserId === $currentUserId) {
						continue;
					}
					$member = $memberMap[$matrixUserId] ?? null;
					$typing[$matrixUserId] = [
						'id' => $matrixUserId,
						'displayName' => $member['displayName'] ?? $this->fallbackUserName($matrixUserId),
					];
				}
			} elseif ($type === 'm.receipt') {
				foreach ($content as $eventId => $receipts) {
					if (!is_string($eventId) || !is_array($receipts)) {
						continue;
					}
					$read = is_array($receipts['m.read'] ?? null) ? $receipts['m.read'] : [];
					foreach ($read as $matrixUserId => $_details) {
						if (!is_string($matrixUserId) || $matrixUserId === '' || $matrixUserId === $currentUserId) {
							continue;
						}
						// The last receipt for a user marks the newest event they have read.
						$readReceipts[$matrixUserId] = $eventId;
					}
				}
			}
		}
		return [
			'typingUsers' => array_values($typing),
			'readReceipts' => $readReceipts,
		];
	}

	/**
	 * @param list<array<string,mixed>> $events
	 * @param list<array{id:string,displayName:string,avatarUrl:string|null,membership:string}> $members
	 * @return list<array<string,mixed>>
	 */
	public function events(array $events, array $members, ?string $currentUserId = null): array {
		$memberMap = [];
		foreach ($members as $member) {
			$memberMap[$member['id']] = $member;
		}
		$replacements = [];
		$reactions = [];
		foreach ($events as $event) {
			if (!is_array($event)) {
				continue;
			}
			$content = is_array($event['content'] ?? null) ? $event['content'] : [];
			$relation = is_array($content['m.relates_to'] ?? null) ? $content['m.relates_to'] : [];
			$target = isset($relation['event_id']) ? (string)$relation['event_id'] : '';
			if (($event['type'] ?? '') === 'm.room.message' && ($relation['rel_type'] ?? '') === 'm.replace' && $target !== '') {
				$newContent = is_array($content['m.new_content'] ?? null) ? $content['m.new_content'] : [];
				$replacements[$target] = (string)($newContent['body'] ?? $content['body'] ?? '');
			}
			if (($event['type'] ?? '') === 'm.reaction' && ($relation['rel_type'] ?? '') === 'm.annotation' && $target !== '') {
				$key = (string)($relation['key'] ?? '');
				if ($key !== '') {
					$reactions[$target][$key] = (int)($reactions[$target][$key] ?? 0) + 1;
				}
			}
		}

		$normalized = [];
		foreach ($events as $event) {
			if (!is_array($event) || ($event['type'] ?? '') !== 'm.room.message') {
				continue;
			}
			$content = is_array($event['content'] ?? null) ? $event['content'] : [];
			$msgtype = (string)($content['msgtype'] ?? '');
			if (!in_array($msgtype, ['m.text', 'm.image', 'm.file', 'm.audio', 'm.video'], true)) {
				continue;
			}
			$relation = is_array($content['m.relates_to'] ?? null) ? $content['m.relates_to'] : [];
			if (($relation['rel_type'] ?? '') === 'm.replace') {
				continue;
			}
			$eventId = (string)($event['event_id'] ?? '');
			$sender = (string)($event['sender'] ?? '');
			$member = $memberMap[$sender] ?? null;
			$mentionsMe = false;
			if ($currentUserId !== null) {
				$mentions = is_array($content['m.mentions'] ?? null) ? $content['m.mentions'] : [];
				$mentionedUserIds = is_array($mentions['user_ids'] ?? null) ? $mentions['user_ids'] : [];
				if (in_array($currentUserId, $mentionedUserIds, true)) {
					$mentionsMe = true;
				} else {
					$formattedBody = (string)($content['formatted_body'] ?? '');
					if ($formattedBody !== '' && str_contains($formattedBody, 'matrix.to/#/' . $currentUserId)) {
						$mentionsMe = true;
					}
				}
			}
			$attachment = $this->attachment($msgtype, $content);
			if ($msgtype !== 'm.text' && $attachment === null) {
				continue;
			}
			$body = (string)($replacements[$eventId] ?? $content['body'] ?? '');
			if (isset($relation['m.in_reply_to']) && is_array($relation['m.in_reply_to'])) {
				$body = $this->stripReplyFallback($body);
			}
			$normalized[] = [
				'id' => $eventId,
				'sender' => $sender,
				'senderName' => $member['displayName'] ?? $this->fallbackUserName($sender),
				'senderAvatarUrl' => $member['avatarUrl'] ?? null,
				'body' => $body,
				'timestamp' => (int)($event['origin_server_ts'] ?? 0),
				'edited' => isset($replacements[$eventId]),
				'mentionsMe' => $mentionsMe,
				'relatesTo' => $relation !== [] ? $relation : null,
				'reactions' => $reactions[$eventId] ?? [],
				'attachment' => $attachment,
			];
		}
		return $normalized;
	}

	/** @param array<string,mixed> $content @return array{kind:string,mxcUrl:string,filename:string,mimeType:string|null,size:int|null}|null */
	private function attachment(string $msgtype, array $content): ?array {
		if ($msgtype === 'm.text') {
			return null;
		}
		$url = (string)($content['url'] ?? '');
		if ($url === '' || preg_match('/^mxc:\/\/[A-Za-z0-9.\-:\[\]]{1,255}\/[A-Za-z0-9_-]{1,255}$/D', $url) !== 1) {
			return null;
		}
		$info = is_array($content['info'] ?? null) ? $content['info'] : [];
		$mimeType = trim((string)($info['mimetype'] ?? ''));
		$size = $info['size'] ?? null;
		return [
			'kind' => substr($msgtype, 2),
			'mxcUrl' => $url,
			'filename' => trim((string)($content['body'] ?? '')) ?: 'attachment',
			'mimeType' => $mimeType !== '' ? $mimeType : null,
			'size' => is_int($size) || (is_string($size) && ctype_digit($size)) ? (int)$size : null,
		];
	}

	/**
	 * @param list<array<string,mixed>> $stateEvents
	 * @param array<string,list<string>> $directRooms
	 * @param list<array{id:string,displayName:string,avatarUrl:string|null,membership:string}> $members
	 * @return array<string,mixed>
	 */
	public function details(string $roomId, array $stateEvents, array $directRooms, string $currentUserId, array $members): array {
		$room = ['state' => ['events' => $stateEvents], 'timeline' => ['events' => []], 'summary' => []];
		$summary = $this->room($roomId, $room, $directRooms, $currentUserId, $members);
		$createEvent = $this->stateEvent($stateEvents, 'm.room.create');
		$creator = is_array($createEvent) ? (string)($createEvent['content']['creator'] ?? $createEvent['sender'] ?? '') : '';
		return [
			'roomId' => $roomId,
			'name' => $summary['name'],
			'avatarUrl' => $summary['avatarUrl'],
			'kind' => $summary['kind'],
			'memberCount' => $summary['memberCount'],
			'topic' => $this->stateString($stateEvents, 'm.room.topic', 'topic') ?? '',
			'canonicalAlias' => $this->stateString($stateEvents, 'm.room.canonical_alias', 'alias'),
			'encrypted' => $summary['encrypted'],
			'creator' => $creator !== '' ? $creator : null,
			'joinRule' => $this->stateString($stateEvents, 'm.room.join_rules', 'join_rule'),
			'historyVisibility' => $this->stateString($stateEvents, 'm.room.history_visibility', 'history_visibility'),
			'members' => $members,
		];
	}

	/** @param array<string,list<string>> $directRooms @param list<array<string,mixed>> $members */
	private function directTarget(string $roomId, array $directRooms, string $currentUserId, array $members): ?string {
		foreach ($directRooms as $matrixUserId => $roomIds) {
			if ($matrixUserId !== $currentUserId && in_array($roomId, $roomIds, true)) {
				return $matrixUserId;
			}
		}
		$joinedOthers = array_values(array_filter(
			$members,
			static fn (array $member): bool => $member['membership'] === 'join' && $member['id'] !== $currentUserId,
		));
		return count($joinedOthers) === 1 && $this->roomIsDirect($roomId, $directRooms)
			? (string)$joinedOthers[0]['id']
			: null;
	}

	/** @param array<string,list<string>> $directRooms */
	private function roomIsDirect(string $roomId, array $directRooms): bool {
		foreach ($directRooms as $roomIds) {
			if (in_array($roomId, $roomIds, true)) {
				return true;
			}
		}
		return false;
	}

	/** @param list<array<string,mixed>> $members */
	private function memberSummary(array $members, string $currentUserId): ?string {
		$names = [];
		foreach ($members as $member) {
			if ($member['membership'] === 'join' && $member['id'] !== $currentUserId) {
				$names[] = $member['displayName'];
			}
		}
		return $names === [] ? null : implode(', ', array_slice($names, 0, 3));
	}

	/** @param list<array<string,mixed>> $events */
	private function stateString(array $events, string $type, string $key): ?string {
		$event = $this->stateEvent($events, $type);
		$value = is_array($event) ? ($event['content'][$key] ?? null) : null;
		return is_string($value) && trim($value) !== '' ? trim($value) : null;
	}

	/** @param list<array<string,mixed>> $events @return array<string,mixed>|null */
	private function stateEvent(array $events, string $type): ?array {
		foreach (array_reverse($events) as $event) {
			if (is_array($event) && ($event['type'] ?? '') === $type) {
				return $event;
			}
		}
		return null;
	}

	private function fallbackUserName(string $matrixUserId): string {
		$localpart = explode(':', ltrim($matrixUserId, '@'), 2)[0] ?? '';
		$localpart = preg_replace('/^ct_/', '', $localpart) ?? $localpart;
		$readable = trim(str_replace(['_', '-'], ' ', $localpart));
		return $readable !== '' ? $readable : 'Unknown user';
	}

	/**
	 * Strip the Matrix rich-reply fallback from a message body: the leading block of
	 * "> " quoted lines (and a single blank separator line) that clients include when
	 * sending an m.in_reply_to message. The remaining text is the actual reply.
	 */
	private function stripReplyFallback(string $body): string {
		$lines = preg_split('/\R/', $body);
		if ($lines === false || $lines === []) {
			return $body;
		}
		$index = 0;
		$stripped = 0;
		while (isset($lines[$index]) && str_starts_with($lines[$index], '>')) {
			$index++;
			$stripped++;
		}
		if ($stripped === 0) {
			return $body;
		}
		if (isset($lines[$index]) && trim($lines[$index]) === '') {
			$index++;
		}
		return trim(implode("\n", array_slice($lines, $index)));
	}
}
