<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;

final class ChurchToolsClientTest extends TestCase {
	private IClientService $clientService;
	private IClient $httpClient;
	private IResponse $response;
	private ChurchToolsClient $client;

	protected function setUp(): void {
		$this->clientService = $this->createMock(IClientService::class);
		$this->httpClient = $this->createMock(IClient::class);
		$this->response = $this->createMock(IResponse::class);
		$this->clientService->method('newClient')->willReturn($this->httpClient);
		$this->client = new ChurchToolsClient($this->clientService);
	}

	public function testGetChatsNormalizesFieldsAndSkipsInvalidEntries(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getBody')->willReturn(json_encode([
			'data' => [
				['creator' => 1, 'domainId' => 9, 'guid' => '681F54E3-2EB7-40A4-84F0-EFF8E8F05727', 'prefix' => 'ctg', 'roomname' => 'Technik', 'status' => 'STARTED'],
				['creator' => null, 'domainId' => 0, 'guid' => '', 'prefix' => 'cte', 'roomname' => null, 'status' => 'STOPPED'],
			],
		], JSON_THROW_ON_ERROR));
		$this->httpClient
			->expects(self::once())
			->method('get')
			->with(
				'https://tenant.church.tools/api/chat',
				self::callback(static fn (array $options): bool => ($options['headers']['Authorization'] ?? '') === 'Login token'),
			)
			->willReturn($this->response);

		$chats = $this->client->getChats('https://tenant.church.tools', 'token');

		self::assertCount(1, $chats);
		self::assertSame(1, $chats[0]['creator']);
		self::assertSame(9, $chats[0]['domainId']);
		self::assertSame('681F54E3-2EB7-40A4-84F0-EFF8E8F05727', $chats[0]['guid']);
		self::assertSame('ctg', $chats[0]['prefix']);
		self::assertSame('Technik', $chats[0]['roomname']);
		self::assertSame('STARTED', $chats[0]['status']);
	}

	public function testGetChatsRawReturnsVerbatimList(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getBody')->willReturn(json_encode([
			'data' => [['guid' => 'X', 'extraField' => 'keep']],
		], JSON_THROW_ON_ERROR));
		$this->httpClient->method('get')->willReturn($this->response);

		$raw = $this->client->getChatsRaw('https://tenant.church.tools', 'token');

		self::assertSame([['guid' => 'X', 'extraField' => 'keep']], $raw);
	}

	public function testUnauthorizedResponseMeansInvalidToken(): void {
		$this->response->method('getStatusCode')->willReturn(401);
		$this->httpClient->method('get')->willReturn($this->response);

		try {
			$this->client->getChatsRaw('https://tenant.church.tools', 'token');
			self::fail('Expected an IntegrationException.');
		} catch (IntegrationException $exception) {
			self::assertSame('invalid_token', $exception->getErrorCode());
			self::assertSame(401, $exception->getHttpStatus());
		}
	}

	public function testForbiddenResponseMeansChurchToolsForbidden(): void {
		$this->response->method('getStatusCode')->willReturn(403);
		$this->httpClient->method('get')->willReturn($this->response);

		try {
			$this->client->getChatsRaw('https://tenant.church.tools', 'token');
			self::fail('Expected an IntegrationException.');
		} catch (IntegrationException $exception) {
			self::assertSame('churchtools_forbidden', $exception->getErrorCode());
			self::assertSame(403, $exception->getHttpStatus());
		}
	}

	public function testSearchGroupsNormalizesSearchResultsAndKeepsFrontendLink(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getBody')->willReturn(json_encode([
			'data' => [
				[
					'domainType' => 'group',
					'domainIdentifier' => '17',
					'title' => 'Technik',
					'frontendUrl' => 'https://tenant.church.tools/?q=groups#/17',
				],
				['domainType' => 'person', 'domainIdentifier' => '18', 'title' => 'Technik'],
			],
		], JSON_THROW_ON_ERROR));
		$this->httpClient->expects(self::once())->method('get')->with(
			self::stringStartsWith('https://tenant.church.tools/api/search?'),
			self::anything(),
		)->willReturn($this->response);

		$groups = $this->client->searchGroups('https://tenant.church.tools', 'token', 'Technik');

		self::assertSame([[
			'id' => 17,
			'name' => 'Technik',
			'frontendUrl' => 'https://tenant.church.tools/?q=groups#/17',
		]], $groups);
	}

	public function testGetGroupUsesDetailEndpointWithRoles(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getBody')->willReturn(json_encode([
			'data' => ['id' => 17, 'name' => 'Technik'],
		], JSON_THROW_ON_ERROR));
		$this->httpClient->expects(self::once())->method('get')->with(
			self::stringStartsWith('https://tenant.church.tools/api/groups/17?'),
			self::anything(),
		)->willReturn($this->response);

		self::assertSame('Technik', $this->client->getGroup('https://tenant.church.tools', 'token', 17)['name']);
	}

	public function testGetGroupMembersUsesSupportedPageSizeAndLoadsAllPages(): void {
		$this->httpClient->expects(self::exactly(2))->method('get')->willReturnCallback(function (string $url): IResponse {
			$response = $this->createMock(IResponse::class);
			$response->method('getStatusCode')->willReturn(200);
			parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
			self::assertSame('100', (string)($query['limit'] ?? ''));
			$page = (int)($query['page'] ?? 0);
			$response->method('getBody')->willReturn(json_encode([
				'data' => [['personId' => $page]],
				'meta' => ['pagination' => ['lastPage' => 2]],
			], JSON_THROW_ON_ERROR));
			return $response;
		});

		$members = $this->client->getGroupMembers('https://tenant.church.tools', 'token', 17);

		self::assertSame([1, 2], array_column($members, 'personId'));
	}
}
