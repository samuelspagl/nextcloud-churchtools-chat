<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\MatrixRoomMapper;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\ProbeService;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

final class ProbeServiceTest extends TestCase {
	public function testCollectReturnsChatsRoomsAndSuggestedMappings(): void {
		$probe = $this->createProbe();

		$data = $probe->collect('admin');

		self::assertSame('https://tenant.church.tools', $data['tenantUrl']);
		self::assertSame([[
			'creator' => 1,
			'domainId' => 9,
			'guid' => 'GUID',
			'prefix' => 'ctg',
			'roomname' => 'Technik',
			'status' => 'STARTED',
		]], $data['churchToolsChats']);
		self::assertSame(
			[['roomId' => '!room:test', 'state' => ['m.room.canonical_alias' => ['alias' => '#ctg_guid:chat.church.tools']]]],
			$data['matrixRooms'],
		);
		self::assertCount(1, $data['suggestedMappings']);
		self::assertSame('#ctg_guid:chat.church.tools', $data['suggestedMappings'][0]['candidateAlias']);
	}

	public function testCollectThrowsWhenUserIsNotConnected(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'churchtools_tenant_url' ? 'https://tenant.church.tools' : $default,
		);
		$config->method('getUserValue')->willReturn('');
		$crypto = $this->createMock(ICrypto::class);
		$clientService = $this->createMock(IClientService::class);
		$appConfigService = new AppConfigService($config, new TenantUrlValidator());
		$matrixUserId = new MatrixUserId($appConfigService);
		$probe = new ProbeService(
			new SecretService($config, $crypto),
			$appConfigService,
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId, $appConfigService),
			new MatrixRoomMapper(),
		);

		$this->expectException(IntegrationException::class);
		$probe->collect('admin');
	}

	private function createProbe(): ProbeService {
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
				'churchtools_token' => 'enc-ct',
				'matrix_access_token' => 'enc-mx',
				default => $default,
			},
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $value): string => match ($value) {
			'enc-ct' => 'ct-token',
			'enc-mx' => 'mx-token',
			default => '',
		});

		$chatResponse = $this->createMock(IResponse::class);
		$chatResponse->method('getStatusCode')->willReturn(200);
		$chatResponse->method('getBody')->willReturn(json_encode([
			'data' => [[
				'creator' => 1,
				'domainId' => 9,
				'guid' => 'GUID',
				'prefix' => 'ctg',
				'roomname' => 'Technik',
				'status' => 'STARTED',
			]],
		], JSON_THROW_ON_ERROR));

		$syncResponse = $this->createMock(IResponse::class);
		$syncResponse->method('getStatusCode')->willReturn(200);
		$syncResponse->method('getBody')->willReturn(json_encode([
			'rooms' => ['join' => ['!room:test' => ['state' => ['events' => [
				['type' => 'm.room.canonical_alias', 'content' => ['alias' => '#ctg_guid:chat.church.tools']],
				['type' => 'm.room.member', 'content' => []],
			]]]]],
		], JSON_THROW_ON_ERROR));

		$httpClient = $this->createMock(IClient::class);
		$httpClient->method('get')->willReturnCallback(
			static function (string $url, array $options) use ($chatResponse, $syncResponse): IResponse {
				if (str_contains($url, '/api/chat')) {
					self::assertSame('Login ct-token', $options['headers']['Authorization'] ?? '');
					return $chatResponse;
				}
				self::assertSame('Bearer mx-token', $options['headers']['Authorization'] ?? '');
				return $syncResponse;
			},
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($httpClient);

		$appConfigService = new AppConfigService($config, new TenantUrlValidator());
		$matrixUserId = new MatrixUserId($appConfigService);

		return new ProbeService(
			new SecretService($config, $crypto),
			$appConfigService,
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, $matrixUserId, $appConfigService),
			new MatrixRoomMapper(),
		);
	}
}