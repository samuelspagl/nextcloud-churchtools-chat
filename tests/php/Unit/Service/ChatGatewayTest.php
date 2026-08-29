<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\ChatGateway;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\MatrixRoomMapper;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCP\Http\Client\IClient;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

final class ChatGatewayTest extends TestCase {
	private const PERSON_GUID = '2E53CF18-8F3B-45B4-95A9-DACFB27FF3DC';
	private const MATRIX_USER_ID = '@ct_2e53cf18-8f3b-45b4-95a9-dacfb27ff3dc:chat.church.tools';

	public function testPersonSearchPrefersMatrixProfileAvatar(): void {
		$gateway = $this->createGateway(200, 'mxc://chat.church.tools/matrix-avatar');

		$results = $gateway->searchPersons('admin', 'Anna');

		self::assertCount(1, $results);
		self::assertSame(self::MATRIX_USER_ID, $results[0]['matrixUserId']);
		self::assertSame('mxc://chat.church.tools/matrix-avatar', $results[0]['imageUrl']);
	}

	public function testPersonSearchKeepsChurchToolsAvatarWhenMatrixProfileFails(): void {
		$gateway = $this->createGateway(404, null);

		$results = $gateway->searchPersons('admin', 'Anna');

		self::assertCount(1, $results);
		self::assertSame('https://tenant.church.tools/images/anna.jpg', $results[0]['imageUrl']);
	}

	public function testGetRoomsUsesOnlyMatrixForNonAdminUsers(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => match ($key) {
				'churchtools_tenant_url' => 'https://tenant.church.tools',
				'matrix_server_url' => 'https://chat.church.tools',
				default => $default,
			},
		);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $userId, string $appId, string $key, string $default): string => match ($key) {
				'churchtools_token' => 'encrypted-non-admin-ct-token',
				'matrix_access_token' => 'encrypted-matrix-token',
				'matrix_user_id' => '@ct_me:chat.church.tools',
				default => $default,
			},
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $value): string => match ($value) {
			'encrypted-matrix-token' => 'matrix-token',
			'encrypted-non-admin-ct-token' => 'non-admin-ct-token',
			default => '',
		});

		$memberEvents = [
			['type' => 'm.room.member', 'state_key' => '@ct_me:chat.church.tools', 'content' => [
				'membership' => 'join',
				'displayname' => 'Me',
				'avatar_url' => 'mxc://chat.church.tools/me',
			]],
			['type' => 'm.room.member', 'state_key' => '@ct_other:chat.church.tools', 'content' => [
				'membership' => 'join',
				'displayname' => 'Other Person',
				'avatar_url' => 'mxc://chat.church.tools/other',
			]],
		];
		$syncResponse = $this->createMock(IResponse::class);
		$syncResponse->method('getStatusCode')->willReturn(200);
		$syncResponse->method('getBody')->willReturn(json_encode([
			'next_batch' => 'next-token',
			'account_data' => ['events' => [[
				'type' => 'm.direct',
				'content' => ['@ct_other:chat.church.tools' => ['!room:chat.church.tools']],
			]]],
			'rooms' => ['join' => [
				'!room:chat.church.tools' => [
					'state' => ['events' => $memberEvents],
					'timeline' => ['events' => [], 'limited' => false],
					'summary' => ['m.joined_member_count' => 2],
				],
			]],
		], JSON_THROW_ON_ERROR));
		$httpClient = $this->createMock(IClient::class);
		$httpClient->expects(self::once())->method('get')->willReturnCallback(
			static function (string $url, array $options) use ($syncResponse): IResponse {
				self::assertStringStartsWith('https://chat.church.tools/_matrix/', $url);
				self::assertStringNotContainsString('tenant.church.tools/api/chat', $url);
				self::assertSame('Bearer matrix-token', $options['headers']['Authorization'] ?? '');
				self::assertStringContainsString('/sync?', $url);
				return $syncResponse;
			},
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($httpClient);
		$appConfigService = new AppConfigService($config, new TenantUrlValidator());
		$matrixUserId = new MatrixUserId($appConfigService);
		$gateway = new ChatGateway(
			new SecretService($config, $crypto),
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId, $appConfigService),
			$matrixUserId,
			new MatrixRoomMapper(),
			$appConfigService,
			$this->createMock(LoggerInterface::class),
		);

		$result = $gateway->getRooms('user');

		self::assertSame('next-token', $result['nextBatch']);
		self::assertCount(1, $result['rooms']);
		self::assertSame('!room:chat.church.tools', $result['rooms'][0]['id']);
		self::assertNull($result['rooms'][0]['lastMessage']);
		self::assertArrayNotHasKey('churchToolsChats', $result);
	}

	public function testGetRoomsFetchesOneProfileForIncompleteDirectTarget(): void {
		$syncResponse = $this->matrixResponse([
			'next_batch' => 'next-token',
			'account_data' => ['events' => [[
				'type' => 'm.direct',
				'content' => ['@ct_other:chat.church.tools' => ['!room:chat.church.tools']],
			]]],
			'rooms' => ['join' => [
				'!room:chat.church.tools' => [
					'state' => ['events' => [[
						'type' => 'm.room.member',
						'state_key' => '@ct_other:chat.church.tools',
						'content' => ['membership' => 'join'],
					]]],
					'timeline' => ['events' => [], 'limited' => false],
					'summary' => ['m.joined_member_count' => 2],
				],
			]],
		]);
		$profileResponse = $this->matrixResponse([
			'displayname' => 'Other Person',
			'avatar_url' => 'mxc://chat.church.tools/other',
		]);
		$httpClient = $this->createMock(IClient::class);
		$httpClient->expects(self::exactly(2))->method('get')->willReturnCallback(
			static function (string $url) use ($syncResponse, $profileResponse): IResponse {
				if (str_contains($url, '/profile/%40ct_other%3Achat.church.tools')) {
					return $profileResponse;
				}
				self::assertStringContainsString('/sync?', $url);
				return $syncResponse;
			},
		);

		$result = $this->createRoomsGateway($httpClient)->getRooms('user');

		self::assertSame('Other Person', $result['rooms'][0]['name']);
		self::assertSame('mxc://chat.church.tools/other', $result['rooms'][0]['avatarUrl']);
	}

	private function matrixResponse(array $body): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn(json_encode($body, JSON_THROW_ON_ERROR));
		return $response;
	}

	private function createRoomsGateway(IClient $httpClient): ChatGateway {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'matrix_server_url' ? 'https://chat.church.tools' : $default,
		);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $userId, string $appId, string $key, string $default): string => match ($key) {
				'matrix_access_token' => 'encrypted-matrix-token',
				'matrix_user_id' => '@ct_me:chat.church.tools',
				default => $default,
			},
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->with('encrypted-matrix-token')->willReturn('matrix-token');
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($httpClient);
		$appConfigService = new AppConfigService($config, new TenantUrlValidator());
		$matrixUserId = new MatrixUserId($appConfigService);
		return new ChatGateway(
			new SecretService($config, $crypto),
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId, $appConfigService),
			$matrixUserId,
			new MatrixRoomMapper(),
			$appConfigService,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function createGateway(int $directoryStatus, ?string $matrixAvatarUrl): ChatGateway {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $userId, string $appId, string $key, string $default): string => match ($key) {
				'churchtools_token' => 'encrypted-ct-token',
				'churchtools_person_id' => '7',
				'matrix_access_token' => 'encrypted-matrix-token',
				default => $default,
			},
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $value): string => match ($value) {
			'encrypted-ct-token' => 'ct-token',
			'encrypted-matrix-token' => 'matrix-token',
			default => '',
		});

		$churchToolsResponse = $this->createMock(IResponse::class);
		$churchToolsResponse->method('getStatusCode')->willReturn(200);
		$churchToolsResponse->method('getBody')->willReturn(json_encode([
			'data' => [[
				'domainType' => 'person',
				'domainIdentifier' => '42',
				'domainAttributes' => [
					'guid' => self::PERSON_GUID,
					'firstName' => 'Anna',
					'lastName' => 'Schmidt',
					'isArchived' => false,
					'dateOfDeath' => null,
				],
				'imageUrl' => 'https://tenant.church.tools/images/anna.jpg',
				'infos' => [],
			]],
		], JSON_THROW_ON_ERROR));

		$matrixResponse = $this->createMock(IResponse::class);
		$matrixResponse->method('getStatusCode')->willReturn($directoryStatus);
		$matrixResponse->method('getBody')->willReturn(json_encode([
			'limited' => false,
			'results' => [[
				'user_id' => self::MATRIX_USER_ID,
				'display_name' => 'Anna Matrix',
				'avatar_url' => $matrixAvatarUrl,
			]],
		], JSON_THROW_ON_ERROR));

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->willReturnCallback(
			static function (string $url, array $options) use ($churchToolsResponse): IResponse {
				self::assertStringStartsWith('https://tenant.church.tools/api/search?', $url);
				self::assertSame('Login ct-token', $options['headers']['Authorization']);
				return $churchToolsResponse;
			},
		);
		$httpClient->method('post')->willReturnCallback(
			static function (string $url, array $options) use ($matrixResponse): IResponse {
				self::assertSame('https://chat.church.tools/_matrix/client/v3/user_directory/search', $url);
				self::assertSame('Bearer matrix-token', $options['headers']['Authorization']);
				self::assertSame([
					'search_term' => 'Anna',
					'limit' => 100,
				], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));
				return $matrixResponse;
			},
		);
		$editResponse = $this->createMock(IResponse::class);
		$editResponse->method('getStatusCode')->willReturn(200);
		$editResponse->method('getBody')->willReturn(json_encode(['event_id' => '$edited:chat.church.tools', 'origin_server_ts' => 1]));
		$httpClient->method('put')->willReturnCallback(
			static function (string $url, array $options) use ($editResponse): IResponse {
				self::assertStringContainsString('/send/m.room.message/', $url);
				self::assertSame('Bearer matrix-token', $options['headers']['Authorization']);
				$body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
				self::assertSame('m.replace', $body['m.relates_to']['rel_type']);
				self::assertSame('$target:chat.church.tools', $body['m.relates_to']['event_id']);
				self::assertSame('new body', $body['body']);
				return $editResponse;
			},
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($httpClient);
		$appConfig = $this->createMock(\OCP\IConfig::class);
		$appConfig->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'churchtools_tenant_url' ? 'https://tenant.church.tools' : $default,
		);
		$appConfigService = new AppConfigService($appConfig, new TenantUrlValidator());
		$matrixUserId = new MatrixUserId($appConfigService);

		return new ChatGateway(
			new SecretService($config, $crypto),
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId, $appConfigService),
			$matrixUserId,
			new MatrixRoomMapper(),
			$appConfigService,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testEditDelegatesReplaceRelationToMatrix(): void {
		$gateway = $this->createGateway(200, 'mxc://chat.church.tools/matrix-avatar');

		$result = $gateway->edit('user', '!room:chat.church.tools', '$target:chat.church.tools', 'new body', null);

		self::assertSame('$edited:chat.church.tools', $result['eventId']);
	}

	public function testSendRejectsAnInvalidMentionUserId(): void {
		$gateway = $this->createGateway(200, 'mxc://chat.church.tools/matrix-avatar');

		$this->expectException(\OCA\ChurchToolsChat\Exception\IntegrationException::class);

		$gateway->send('user', '!room:chat.church.tools', 'hi', null, null, ['not-a-matrix-id']);
	}

	public function testGetMessageFetchesEventAndResolvesSender(): void {
		$gateway = $this->createMessageGateway();

		$result = $gateway->getMessage('admin', '!room:chat.church.tools', '$target:chat.church.tools');

		self::assertSame('$target:chat.church.tools', $result['id']);
		self::assertSame('Anna Schmidt', $result['senderName']);
		self::assertSame('original', $result['body']);
	}

	public function testSetTypingDelegatesTypingStateToMatrix(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $userId, string $appId, string $key, string $default): string => match ($key) {
				'matrix_access_token' => 'encrypted-matrix-token',
				'matrix_user_id' => '@ct_me:chat.church.tools',
				default => $default,
			},
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $value): string => match ($value) {
			'encrypted-matrix-token' => 'matrix-token',
			default => '',
		});
		$typingResponse = $this->createMock(IResponse::class);
		$typingResponse->method('getStatusCode')->willReturn(200);
		$typingResponse->method('getBody')->willReturn('{}');
		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('put')->willReturnCallback(
			static function (string $url, array $options) use ($typingResponse): IResponse {
				self::assertSame('https://chat.church.tools/_matrix/client/v3/rooms/%21room%3Achat.church.tools/typing/%40ct_me%3Achat.church.tools', $url);
				self::assertSame('Bearer matrix-token', $options['headers']['Authorization']);
				self::assertSame(['typing' => true, 'timeout' => 30000], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));
				return $typingResponse;
			},
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($httpClient);
		$appConfig = $this->createMock(\OCP\IConfig::class);
		$appConfig->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'churchtools_tenant_url' ? 'https://tenant.church.tools' : $default,
		);
		$appConfigService = new AppConfigService($appConfig, new TenantUrlValidator());
		$matrixUserId = new MatrixUserId($appConfigService);

		$gateway = new ChatGateway(
			new SecretService($config, $crypto),
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId, $appConfigService),
			$matrixUserId,
			new MatrixRoomMapper(),
			$appConfigService,
			$this->createMock(LoggerInterface::class),
		);

		$gateway->setTyping('admin', '!room:chat.church.tools', true);
	}

	private function createMessageGateway(): ChatGateway {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $userId, string $appId, string $key, string $default): string => match ($key) {
				'matrix_access_token' => 'encrypted-matrix-token',
				'matrix_user_id' => '@ct_me:chat.church.tools',
				default => $default,
			},
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $value): string => match ($value) {
			'encrypted-matrix-token' => 'matrix-token',
			default => '',
		});
		$eventResponse = $this->createMock(IResponse::class);
		$eventResponse->method('getStatusCode')->willReturn(200);
		$eventResponse->method('getBody')->willReturn(json_encode([
			'type' => 'm.room.message',
			'event_id' => '$target:chat.church.tools',
			'sender' => '@ct_anna:chat.church.tools',
			'origin_server_ts' => 1000,
			'content' => ['msgtype' => 'm.text', 'body' => 'original'],
		], JSON_THROW_ON_ERROR));
		$membersResponse = $this->createMock(IResponse::class);
		$membersResponse->method('getStatusCode')->willReturn(200);
		$membersResponse->method('getBody')->willReturn(json_encode([
			'chunk' => [[
				'type' => 'm.room.member',
				'state_key' => '@ct_anna:chat.church.tools',
				'content' => [
					'membership' => 'join',
					'displayname' => 'Anna Schmidt',
					'avatar_url' => 'mxc://chat.church.tools/anna',
				],
			]],
		], JSON_THROW_ON_ERROR));
		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->willReturnCallback(
			static function (string $url, array $options) use ($eventResponse, $membersResponse): IResponse {
				self::assertSame('Bearer matrix-token', $options['headers']['Authorization']);
				return str_contains($url, '/event/') ? $eventResponse : $membersResponse;
			},
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($httpClient);
		$appConfig = $this->createMock(\OCP\IConfig::class);
		$appConfig->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'churchtools_tenant_url' ? 'https://tenant.church.tools' : $default,
		);
		$appConfigService = new AppConfigService($appConfig, new TenantUrlValidator());
		$matrixUserId = new MatrixUserId($appConfigService);

		return new ChatGateway(
			new SecretService($config, $crypto),
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId, $appConfigService),
			$matrixUserId,
			new MatrixRoomMapper(),
			$appConfigService,
			$this->createMock(LoggerInterface::class),
		);
	}
}
