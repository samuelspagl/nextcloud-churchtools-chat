<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use JsonException;
use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCP\Http\Client\IClientService;
use Throwable;

final class MatrixClient {
	private const AVATAR_SIZE = 128;
	private const MAX_AVATAR_BYTES = 5 * 1024 * 1024;
	private const MAX_MEDIA_BYTES = 50 * 1024 * 1024;
	private const IMAGE_CONTENT_TYPES = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/avif',
	];

	public function __construct(
		private readonly IClientService $clientService,
		private readonly MatrixUserId $matrixUserId,
		private readonly AppConfigService $appConfig,
	) {
	}

	private function baseUrl(): string {
		return $this->appConfig->getMatrixBaseUrl();
	}

	/** @return array{access_token:string,user_id:string,device_id?:string} */
	public function bootstrap(string $personGuid, string $chatPassword): array {
		if ($chatPassword === '' || strlen($chatPassword) > 4096) {
			throw new IntegrationException('invalid_matrix_password', 'Enter a valid CT Chat password.', 400);
		}

		$matrixUserId = $this->matrixUserId->fromChurchToolsGuid($personGuid);
		$result = $this->request('POST', '/_matrix/client/v3/login', null, [
			'type' => 'm.login.password',
			'identifier' => ['type' => 'm.id.user', 'user' => $matrixUserId],
			'password' => $chatPassword,
			'initial_device_display_name' => 'Nextcloud ChurchTools Chat',
		], false);

		if (!isset($result['access_token'], $result['user_id'])) {
			throw new IntegrationException(
				'matrix_login_failed',
				'The Matrix login was rejected. Check the CT Chat password.',
				409,
			);
		}

		return [
			'access_token' => (string)$result['access_token'],
			'user_id' => (string)$result['user_id'],
			'device_id' => isset($result['device_id']) ? (string)$result['device_id'] : '',
		];
	}

	/** @return array<string,mixed> */
	public function sync(string $accessToken, ?string $since = null, int $timeout = 0): array {
		$query = ['timeout' => min(max($timeout, 0), 25000), 'filter' => json_encode(['room' => ['timeline' => ['limit' => 30], 'state' => ['lazy_load_members' => true]]])];
		if ($since !== null && $since !== '') {
			$query['since'] = $since;
		}
		return $this->request('GET', '/_matrix/client/v3/sync?' . http_build_query($query), $accessToken);
	}

	/** @return array<string,mixed> */
	public function messages(string $accessToken, string $roomId, ?string $from = null, int $limit = 50): array {
		$query = ['dir' => 'b', 'limit' => min(max($limit, 1), 100)];
		if ($from !== null && $from !== '') {
			$query['from'] = $from;
		}
		return $this->request('GET', '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/messages?' . http_build_query($query), $accessToken);
	}

	/** @return list<array<string,mixed>> */
	public function searchRoomMessages(string $accessToken, string $roomId, string $query, int $limit = 20): array {
		$result = $this->request(
			'POST',
			'/_matrix/client/v3/search',
			$accessToken,
			[
				'search_categories' => [
					'room_events' => [
						'search_term' => $query,
						'keys' => ['content.body'],
						'filter' => [
							'rooms' => [$roomId],
							'limit' => min(max($limit, 1), 50),
						],
					],
				],
			],
		);
		$results = $result['search_categories']['room_events']['results'] ?? [];
		if (!is_array($results)) {
			return [];
		}
		$events = [];
		foreach ($results as $entry) {
			if (is_array($entry) && is_array($entry['result'] ?? null)) {
				$events[] = $entry['result'];
			}
		}
		return $events;
	}

	/** @return list<array{roomId:string,event:array<string,mixed>}> */
	public function searchMessages(string $accessToken, string $query, int $limit = 20): array {
		$result = $this->request(
			'POST',
			'/_matrix/client/v3/search',
			$accessToken,
			[
				'search_categories' => [
					'room_events' => [
						'search_term' => $query,
						'keys' => ['content.body'],
						'filter' => ['limit' => min(max($limit, 1), 50)],
					],
				],
			],
		);
		$entries = $result['search_categories']['room_events']['results'] ?? [];
		if (!is_array($entries)) {
			return [];
		}
		$results = [];
		foreach ($entries as $entry) {
			$event = is_array($entry) ? ($entry['result'] ?? null) : null;
			$roomId = is_array($event) ? ($event['room_id'] ?? null) : null;
			if (!is_array($event) || !is_string($roomId) || $roomId === '') {
				continue;
			}
			$results[] = ['roomId' => $roomId, 'event' => $event];
		}
		return $results;
	}

	/** @return list<array<string,mixed>> */
	public function roomState(string $accessToken, string $roomId): array {
		$result = $this->request('GET', '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/state', $accessToken);
		return array_values(array_filter($result, 'is_array'));
	}

	/** @return list<array<string,mixed>> */
	public function roomMembers(string $accessToken, string $roomId): array {
		try {
			$result = $this->request(
				'GET',
				'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/members?' . http_build_query(['membership' => 'join']),
				$accessToken,
			);
			$events = $result['chunk'] ?? [];
			return is_array($events) ? array_values(array_filter($events, 'is_array')) : [];
		} catch (IntegrationException) {
			// Member metadata enriches the UI but must never make messages unavailable.
			return [];
		}
	}

	/** @return array<string,mixed> */
	public function event(string $accessToken, string $roomId, string $eventId): array {
		return $this->request(
			'GET',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/event/' . rawurlencode($eventId),
			$accessToken,
		);
	}

	/** @return array<string,list<string>> */
	public function getDirectRooms(string $accessToken, string $currentUserId): array {
		try {
			$result = $this->request(
				'GET',
				'/_matrix/client/v3/user/' . rawurlencode($currentUserId) . '/account_data/m.direct',
				$accessToken,
			);
			$directRooms = [];
			foreach ($result as $matrixUserId => $roomIds) {
				if (!is_string($matrixUserId) || !is_array($roomIds)) {
					continue;
				}
				$directRooms[$matrixUserId] = array_values(array_filter($roomIds, 'is_string'));
			}
			return $directRooms;
		} catch (IntegrationException) {
			return [];
		}
	}

	/** @return array{displayname?:string,avatar_url?:string}|null */
	public function profile(string $accessToken, string $matrixUserId): ?array {
		try {
			$result = $this->request('GET', '/_matrix/client/v3/profile/' . rawurlencode($matrixUserId), $accessToken);
			return [
				'displayname' => isset($result['displayname']) ? (string)$result['displayname'] : '',
				'avatar_url' => isset($result['avatar_url']) ? (string)$result['avatar_url'] : '',
			];
		} catch (IntegrationException) {
			// Global profiles are a fallback for missing room-local member state.
			return null;
		}
	}

	/** @return array<string,array{displayname:string,avatar_url:string}> */
	public function searchUserDirectory(string $accessToken, string $query, int $limit = 50): array {
		try {
			$result = $this->request(
				'POST',
				'/_matrix/client/v3/user_directory/search',
				$accessToken,
				[
					'search_term' => $query,
					'limit' => min(max($limit, 1), 100),
				],
			);
			$profiles = [];
			foreach (is_array($result['results'] ?? null) ? $result['results'] : [] as $profile) {
				if (!is_array($profile) || !is_string($profile['user_id'] ?? null)) {
					continue;
				}
				$profiles[$profile['user_id']] = [
					'displayname' => is_string($profile['display_name'] ?? null) ? $profile['display_name'] : '',
					'avatar_url' => is_string($profile['avatar_url'] ?? null) ? $profile['avatar_url'] : '',
				];
			}
			return $profiles;
		} catch (IntegrationException) {
			// Directory metadata enriches ChurchTools search results but is optional.
			return [];
		}
	}

	/** @return array{body:string,contentType:string,etag:string} */
	public function thumbnail(string $accessToken, string $mxcUri): array {
		return $this->imageThumbnail($accessToken, $mxcUri, self::AVATAR_SIZE, self::AVATAR_SIZE);
	}

	/** @return array{body:string,contentType:string,etag:string} */
	public function imageThumbnail(string $accessToken, string $mxcUri, int $width = 160, int $height = 160): array {
		[$serverName, $mediaId] = $this->parseMxcUri($mxcUri);
		// CT Chat's Synapse exposes media via the Matrix media repository. The
		// authenticated client-media endpoints return 401 for otherwise public MXC
		// attachments, so do not use them for previews or downloads.
		$path = '/_matrix/media/v3/thumbnail/'
			. rawurlencode($serverName)
			. '/'
			. rawurlencode($mediaId)
			. '?'
			. http_build_query([
				'width' => min(max($width, 32), 512),
				'height' => min(max($height, 32), 512),
				'method' => 'crop',
				'animated' => 'false',
				'allow_redirect' => 'false',
			]);
		$options = [
			'headers' => [
				'Accept' => implode(', ', self::IMAGE_CONTENT_TYPES),
				'Authorization' => 'Bearer ' . $accessToken,
			],
			'allow_redirects' => false,
			'connect_timeout' => 5,
			'timeout' => 15,
			'http_errors' => false,
			'stream' => true,
		];

		try {
			$response = $this->clientService->newClient()->get($this->baseUrl() . $path, $options);
			$status = $response->getStatusCode();
			if ($status === 401 || $status === 403) {
				throw new IntegrationException('matrix_session_expired', 'The Matrix session is unavailable or expired.', 401);
			}
			if ($status === 404) {
				throw new IntegrationException('matrix_media_not_found', 'The Matrix image was not found.', 404);
			}
			if ($status < 200 || $status >= 300) {
				throw new IntegrationException('matrix_media_failed', 'Matrix could not provide the image.', 502);
			}

			$contentLength = $response->getHeader('Content-Length');
			if ($contentLength !== '' && ctype_digit($contentLength) && (int)$contentLength > self::MAX_AVATAR_BYTES) {
				throw new IntegrationException('matrix_media_too_large', 'The Matrix image is too large.', 413);
			}

			$contentType = strtolower(trim(explode(';', $response->getHeader('Content-Type'), 2)[0]));
			if (!in_array($contentType, self::IMAGE_CONTENT_TYPES, true)) {
				throw new IntegrationException('matrix_media_type_unsupported', 'The Matrix image has an unsupported image type.', 415);
			}

			$body = $response->getBody();
			if (is_resource($body)) {
				$data = stream_get_contents($body, self::MAX_AVATAR_BYTES + 1);
			} else {
				$data = is_string($body) ? $body : false;
			}
			if ($data === false || $data === '') {
				throw new IntegrationException('invalid_matrix_media', 'Matrix returned an invalid avatar.', 502);
			}
			if (strlen($data) > self::MAX_AVATAR_BYTES) {
				throw new IntegrationException('matrix_media_too_large', 'The Matrix avatar is too large.', 413);
			}

			return [
				'body' => $data,
				'contentType' => $contentType,
				'etag' => hash('sha256', $mxcUri),
			];
		} catch (IntegrationException $e) {
			throw $e;
		} catch (Throwable) {
			throw new IntegrationException('matrix_unavailable', 'Matrix is currently unavailable.', 502);
		}
	}

	/** @return array{body:string,contentType:string,etag:string} */
	public function media(string $accessToken, string $mxcUri): array {
		[$serverName, $mediaId] = $this->parseMxcUri($mxcUri);
		$options = [
			'headers' => ['Authorization' => 'Bearer ' . $accessToken],
			'allow_redirects' => false,
			'connect_timeout' => 5,
			'timeout' => 35,
			'http_errors' => false,
			'stream' => true,
		];
		try {
			$response = $this->clientService->newClient()->get($this->baseUrl() . '/_matrix/media/v3/download/' . rawurlencode($serverName) . '/' . rawurlencode($mediaId), $options);
			$status = $response->getStatusCode();
			if ($status === 401 || $status === 403) throw new IntegrationException('matrix_session_expired', 'The Matrix session is unavailable or expired.', 401);
			if ($status === 404) throw new IntegrationException('matrix_media_not_found', 'The Matrix attachment was not found.', 404);
			if ($status < 200 || $status >= 300) throw new IntegrationException('matrix_media_failed', 'Matrix could not provide the attachment.', 502);
			$contentLength = $response->getHeader('Content-Length');
			if ($contentLength !== '' && ctype_digit($contentLength) && (int)$contentLength > self::MAX_MEDIA_BYTES) throw new IntegrationException('matrix_media_too_large', 'The Matrix attachment is too large.', 413);
			$contentType = strtolower(trim(explode(';', $response->getHeader('Content-Type'), 2)[0]));
			if ($contentType === '' || strlen($contentType) > 255) throw new IntegrationException('matrix_media_type_unsupported', 'Matrix returned an invalid attachment type.', 415);
			$body = $response->getBody();
			$data = is_resource($body) ? stream_get_contents($body, self::MAX_MEDIA_BYTES + 1) : (is_string($body) ? $body : false);
			if ($data === false || $data === '') throw new IntegrationException('invalid_matrix_media', 'Matrix returned an invalid attachment.', 502);
			if (strlen($data) > self::MAX_MEDIA_BYTES) throw new IntegrationException('matrix_media_too_large', 'The Matrix attachment is too large.', 413);
			return ['body' => $data, 'contentType' => $contentType, 'etag' => hash('sha256', 'download:' . $mxcUri)];
		} catch (IntegrationException $e) {
			throw $e;
		} catch (Throwable) {
			throw new IntegrationException('matrix_unavailable', 'Matrix is currently unavailable.', 502);
		}
	}

	public function createDirectRoom(string $accessToken, string $targetUserId): string {
		$result = $this->request('POST', '/_matrix/client/v3/createRoom', $accessToken, [
			'preset' => 'trusted_private_chat',
			'is_direct' => true,
			'invite' => [$targetUserId],
		]);
		$roomId = (string)($result['room_id'] ?? '');
		if ($roomId === '') {
			throw new IntegrationException('matrix_room_creation_failed', 'Matrix did not return a room identifier.', 502);
		}
		return $roomId;
	}

	/** @param array<string,list<string>> $directRooms */
	public function setDirectRooms(string $accessToken, string $currentUserId, array $directRooms): void {
		if ($currentUserId === '' || strlen($currentUserId) > 255) {
			throw new IntegrationException('invalid_matrix_session', 'The Matrix session has an invalid user identifier.', 409);
		}
		$this->request(
			'PUT',
			'/_matrix/client/v3/user/' . rawurlencode($currentUserId) . '/account_data/m.direct',
			$accessToken,
			$directRooms,
		);
	}

	/** @return array<string,mixed> */
	/** @param list<string>|null $mentions */
	public function sendMessage(string $accessToken, string $roomId, string $body, string $transactionId, ?string $replyTo = null, ?array $mentions = null): array {
		$content = ['msgtype' => 'm.text', 'body' => $body];
		if ($replyTo !== null) {
			$content['m.relates_to'] = ['m.in_reply_to' => ['event_id' => $replyTo]];
		}
		if ($mentions !== null && $mentions !== []) {
			$content['m.mentions'] = ['user_ids' => $mentions];
		}
		return $this->request(
			'PUT',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.room.message/' . rawurlencode($transactionId),
			$accessToken,
			$content,
		);
	}

	/** @return array<string,mixed> */
	public function react(string $accessToken, string $roomId, string $eventId, string $emoji, string $transactionId): array {
		return $this->request(
			'PUT',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.reaction/' . rawurlencode($transactionId),
			$accessToken,
			['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => $eventId, 'key' => $emoji]],
		);
	}

	/** @return array<string,mixed> */
	public function editMessage(string $accessToken, string $roomId, string $eventId, string $body, string $transactionId): array {
		return $this->request(
			'PUT',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.room.message/' . rawurlencode($transactionId),
			$accessToken,
			[
				'msgtype' => 'm.text',
				'body' => $body,
				'new_content' => ['msgtype' => 'm.text', 'body' => $body],
				'm.relates_to' => ['rel_type' => 'm.replace', 'event_id' => $eventId],
			],
		);
	}

	public function setFullyRead(string $accessToken, string $roomId, string $eventId): void {
		// Private fully-read marker.
		$this->request(
			'PUT',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/read_markers',
			$accessToken,
			['m.fully_read' => $eventId],
		);
	}

	public function sendReadReceipt(string $accessToken, string $roomId, string $eventId): void {
		// Public read receipt (visible to other sessions, drives notification_count).
		// POST with thread_id:"main" matches Element / chat.church.tools.
		$this->request(
			'POST',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/receipt/m.read/' . rawurlencode($eventId),
			$accessToken,
			['thread_id' => 'main'],
		);
	}

	public function sendTyping(string $accessToken, string $roomId, string $userId, bool $typing, int $timeout = 30000): void {
		$body = ['typing' => $typing];
		if ($typing) {
			$body['timeout'] = min(max($timeout, 1000), 30000);
		}
		$this->request(
			'PUT',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/typing/' . rawurlencode($userId),
			$accessToken,
			$body,
		);
	}

	/**
	 * Resolve a room alias to a room id via the room directory, or null when the
	 * alias does not exist on the homeserver.
	 */
	public function resolveRoomAlias(string $accessToken, string $alias): ?string {
		try {
			$result = $this->request('GET', '/_matrix/client/v3/directory/room/' . rawurlencode($alias), $accessToken);
			$roomId = $result['room_id'] ?? null;
			return is_string($roomId) && $roomId !== '' ? $roomId : null;
		} catch (IntegrationException) {
			return null;
		}
	}

	/** @return array<string,mixed> */
	public function redact(string $accessToken, string $roomId, string $eventId, string $transactionId): array {
		return $this->request(
			'PUT',
			'/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/redact/' . rawurlencode($eventId) . '/' . rawurlencode($transactionId),
			$accessToken,
			// Synapse rejects an empty body with M_NOT_JSON, so send an empty JSON object.
			(object)[],
		);
	}

	/** @return array<string,mixed> */
	private function request(string $method, string $path, ?string $accessToken = null, array|object|null $body = null, bool $mapAuthFailure = true): array {
		$options = [
			'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
			'connect_timeout' => 5,
			'timeout' => 35,
			'http_errors' => false,
		];
		if ($accessToken !== null && $accessToken !== '') {
			$options['headers']['Authorization'] = 'Bearer ' . $accessToken;
		}
		if ($body !== null) {
			$options['body'] = json_encode($body, JSON_THROW_ON_ERROR);
		}

		try {
			$client = $this->clientService->newClient();
			$response = match ($method) {
				'GET' => $client->get($this->baseUrl() . $path, $options),
				'POST' => $client->post($this->baseUrl() . $path, $options),
				'PUT' => $client->put($this->baseUrl() . $path, $options),
				default => throw new IntegrationException('unsupported_method', 'Unsupported Matrix request method.', 500),
			};
			$status = $response->getStatusCode();
			$data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($data)) {
				$data = [];
			}

			if ($status === 401 || $status === 403) {
				if (!$mapAuthFailure) {
					return $data;
				}
				throw new IntegrationException('matrix_session_expired', 'The Matrix session is unavailable or expired.', 401);
			}
			if ($status === 429) {
				$retryAfter = null;
				$retryAfterHeader = $response->getHeader('Retry-After');
				if ($retryAfterHeader !== '' && is_numeric($retryAfterHeader)) {
					$retryAfter = (int)$retryAfterHeader;
				}
				throw new IntegrationException('matrix_rate_limited', 'Matrix rate limited the request.', 429, $retryAfter);
			}
			if ($status < 200 || $status >= 300) {
				$errcode = is_string($data['errcode'] ?? null) && $data['errcode'] !== ''
					? (string)$data['errcode']
					: 'HTTP ' . $status;
				$error = is_string($data['error'] ?? null) && $data['error'] !== ''
					? (string)$data['error']
					: 'Matrix could not complete the request.';
				throw new IntegrationException('matrix_request_failed', $errcode . ': ' . $error, 502);
			}
			return $data;
		} catch (IntegrationException $e) {
			throw $e;
		} catch (JsonException) {
			throw new IntegrationException('invalid_matrix_response', 'Matrix returned malformed JSON.', 502);
		} catch (Throwable) {
			throw new IntegrationException('matrix_unavailable', 'Matrix is currently unavailable.', 502);
		}
	}

	/** @return array{string,string} */
	private function parseMxcUri(string $mxcUri): array {
		if (strlen($mxcUri) > 520
			|| preg_match('/^mxc:\/\/([A-Za-z0-9.\-:\[\]]{1,255})\/([A-Za-z0-9_-]{1,255})$/D', $mxcUri, $matches) !== 1) {
			throw new IntegrationException('invalid_mxc_uri', 'The Matrix avatar URI is invalid.', 400);
		}

		return [$matches[1], $matches[2]];
	}
}
