<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCA\ChurchToolsChat\Service\GroupContextService;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\RoomDetailsProvider;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\Security\ICrypto;
use OCP\Teams\ITeamManager;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\Team;
use OCP\Teams\TeamResource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GroupContextServiceTest extends TestCase {
	public function testDirectRoomIsNotApplicableWithoutExternalRequests(): void {
		$roomProvider = $this->createMock(RoomDetailsProvider::class);
		$roomProvider->method('getRoomDetails')->willReturn(['kind' => 'direct', 'name' => 'Anna']);
		$client = $this->createMock(IClient::class);
		$client->expects(self::never())->method('get');

		$result = $this->service($roomProvider, $client)->getForRoom('sam', '!room:server');

		self::assertSame('not_applicable', $result['matchStatus']);
		self::assertNull($result['group']);
	}

	public function testAmbiguousNormalizedNameDoesNotSelectAGroup(): void {
		$roomProvider = $this->groupRoom('  Technik  Team ');
		$client = $this->clientFor([
			'/api/search?' => ['data' => [
				['domainType' => 'group', 'domainIdentifier' => '17', 'title' => 'technik team'],
				['domainType' => 'group', 'domainIdentifier' => '18', 'title' => "TECHNIK\tTEAM"],
			]],
		]);

		$result = $this->service($roomProvider, $client)->getForRoom('sam', '!room:server');

		self::assertSame('ambiguous', $result['matchStatus']);
		self::assertNull($result['group']);
	}

	public function testMatchedGroupIncludesOriginalLeadershipRolesAndTeamResources(): void {
		$roomProvider = $this->groupRoom('Technik Team', [[
			'id' => '@ct_11111111-1111-1111-1111-111111111111:chat.church.tools',
			'avatarUrl' => 'mxc://chat.church.tools/AnnaAvatar',
		]]);
		$client = $this->clientFor([
			'/api/search?' => ['data' => [[
				'domainType' => 'group',
				'domainIdentifier' => '17',
				'title' => ' technik   team ',
				'frontendUrl' => 'https://tenant.church.tools/?q=groups#/17',
			]]],
			'/api/groups/17?' => ['data' => [
				'id' => 17,
				'name' => 'Technik Team',
				'visibility' => 'internal',
				'information' => ['groupTypeId' => 3, 'groupCategoryId' => 4, 'note' => 'Sound and lights'],
				'roles' => [
					['id' => 8, 'groupTypeRoleId' => 80, 'name' => 'Bereichsleitung', 'isLeader' => true],
					['id' => 9, 'groupTypeRoleId' => 90, 'name' => 'Mitarbeit', 'isLeader' => false],
				],
			]],
			'/api/groups/17/members?' => ['data' => [[
				'personId' => 42,
				'groupTypeRoleId' => 80,
				'groupMemberStatus' => ['name' => 'active'],
				'person' => ['id' => 42, 'firstName' => 'Anna', 'lastName' => 'Schmidt', 'guid' => '11111111-1111-1111-1111-111111111111'],
			]]],
			'/api/group/grouptypes' => ['data' => [['id' => 3, 'nameTranslated' => 'Dienstgruppe']]],
			'/api/group/groupcategories/4' => ['data' => ['id' => 4, 'nameTranslated' => 'Technik']],
		]);

		$teamManager = $this->createMock(ITeamManager::class);
		$teamManager->method('hasTeamSupport')->willReturn(true);
		$teamManager->method('getTeamsForUser')->willReturn([
			new Team('team-1', ' technik  team ', '/apps/teams/team-1'),
			new Team('team-2', 'Other', '/apps/teams/team-2'),
		]);
		$files = $this->provider('files', 'Files');
		$deck = $this->provider('deck', 'Deck');
		$other = $this->provider('calendar', 'Calendar');
		$teamManager->method('getSharedWith')->with('team-1', 'sam')->willReturn([
			new TeamResource($files, 'folder-1', 'Shared folder', '/f/1'),
			new TeamResource($deck, 'board-1', 'Planning', '/apps/deck/board/1'),
			new TeamResource($other, 'calendar-1', 'Calendar', '/calendar/1'),
		]);

		$result = $this->service($roomProvider, $client, $teamManager)->getForRoom('sam', '!room:server');

		self::assertSame('matched', $result['matchStatus']);
		self::assertSame('internal', $result['group']['visibility']);
		self::assertSame('Dienstgruppe', $result['group']['groupType']);
		self::assertSame('Technik', $result['group']['category']);
		self::assertSame('Bereichsleitung', $result['group']['leadership'][0]['roleName']);
		self::assertSame('Anna Schmidt', $result['group']['leadership'][0]['members'][0]['displayName']);
		self::assertSame('mxc://chat.church.tools/AnnaAvatar', $result['group']['leadership'][0]['members'][0]['avatarUrl']);
		self::assertSame(1, $result['group']['memberCount']);
		self::assertSame(['folder', 'deck-board'], array_column($result['nextcloud']['teams'][0]['resources'], 'kind'));
	}

	public function testTeamFailureIsReturnedAsPartialError(): void {
		$roomProvider = $this->groupRoom('Technik');
		$client = $this->clientFor([
			'/api/search?' => ['data' => [['domainType' => 'group', 'domainIdentifier' => '17', 'title' => 'Technik']]],
			'/api/groups/17?' => ['data' => ['id' => 17, 'name' => 'Technik']],
			'/api/groups/17/members?' => ['data' => []],
			'/api/group/grouptypes' => ['data' => []],
		]);
		$teamManager = $this->createMock(ITeamManager::class);
		$teamManager->method('hasTeamSupport')->willReturn(true);
		$teamManager->method('getTeamsForUser')->willThrowException(new RuntimeException('Teams unavailable'));

		$result = $this->service($roomProvider, $client, $teamManager)->getForRoom('sam', '!room:server');

		self::assertSame('matched', $result['matchStatus']);
		self::assertSame('error', $result['nextcloud']['status']);
	}

	public function testMissingTeamSupportIsReportedAsUnavailable(): void {
		$roomProvider = $this->groupRoom('Technik');
		$client = $this->clientFor([
			'/api/search?' => ['data' => [['domainType' => 'group', 'domainIdentifier' => '17', 'title' => 'Technik']]],
			'/api/groups/17?' => ['data' => ['id' => 17, 'name' => 'Technik']],
			'/api/groups/17/members?' => ['data' => []],
			'/api/group/grouptypes' => ['data' => []],
		]);
		$teamManager = $this->createMock(ITeamManager::class);
		$teamManager->method('hasTeamSupport')->willReturn(false);
		$teamManager->expects(self::never())->method('getTeamsForUser');

		$result = $this->service($roomProvider, $client, $teamManager)->getForRoom('sam', '!room:server');

		self::assertSame('unavailable', $result['nextcloud']['status']);
	}

	/** @param list<array<string,mixed>> $members */
	private function groupRoom(string $name, array $members = []): RoomDetailsProvider {
		$provider = $this->createMock(RoomDetailsProvider::class);
		$provider->method('getRoomDetails')->willReturn(['kind' => 'group', 'name' => $name, 'members' => $members]);
		return $provider;
	}

	/** @param array<string,array<string,mixed>> $responses */
	private function clientFor(array $responses): IClient {
		$client = $this->createMock(IClient::class);
		$client->method('get')->willReturnCallback(function (string $url) use ($responses): IResponse {
			foreach ($responses as $path => $body) {
				if (!str_contains($url, $path)) continue;
				$response = $this->createMock(IResponse::class);
				$response->method('getStatusCode')->willReturn(200);
				$response->method('getBody')->willReturn(json_encode($body, JSON_THROW_ON_ERROR));
				return $response;
			}
			throw new RuntimeException('Unexpected URL: ' . $url);
		});
		return $client;
	}

	private function provider(string $id, string $name): ITeamResourceProvider {
		$provider = $this->createMock(ITeamResourceProvider::class);
		$provider->method('getId')->willReturn($id);
		$provider->method('getName')->willReturn($name);
		return $provider;
	}

	private function service(RoomDetailsProvider $roomProvider, IClient $client, ?ITeamManager $teamManager = null): GroupContextService {
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'churchtools_tenant_url' ? 'https://tenant.church.tools' : $default,
		);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $userId, string $appId, string $key, string $default): string => $key === 'churchtools_token' ? 'encrypted-token' : $default,
		);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->with('encrypted-token')->willReturn('token');
		$appConfig = new AppConfigService($config, new TenantUrlValidator());

		return new GroupContextService(
			$roomProvider,
			new ChurchToolsClient($clientService),
			new SecretService($config, $crypto),
			$appConfig,
			$teamManager ?? $this->createMock(ITeamManager::class),
			new MatrixUserId($appConfig),
		);
	}
}
