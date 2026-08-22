<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Service\ChatGateway;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\MatrixRoomMapper;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\SecretService;
use OCP\Http\Client\IClient;
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
				'tenant_url' => 'https://tenant.church.tools',
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
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($httpClient);
		$matrixUserId = new MatrixUserId();

		return new ChatGateway(
			new SecretService($config, $crypto),
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId),
			$matrixUserId,
			new MatrixRoomMapper(),
		);
	}
}
