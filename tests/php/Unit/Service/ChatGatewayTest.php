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
