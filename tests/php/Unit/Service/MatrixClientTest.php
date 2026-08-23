<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

final class MatrixClientTest extends TestCase {
	private IClientService $clientService;
	private IClient $httpClient;
	private IResponse $response;
	private MatrixClient $matrix;

	protected function setUp(): void {
		$this->clientService = $this->createMock(IClientService::class);
		$this->httpClient = $this->createMock(IClient::class);
		$this->response = $this->createMock(IResponse::class);
		$this->clientService->method('newClient')->willReturn($this->httpClient);
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'matrix_server_url' ? $default : '',
		);
		$appConfig = new AppConfigService($config, new TenantUrlValidator());
		$this->matrix = new MatrixClient($this->clientService, new MatrixUserId($appConfig), $appConfig);
	}

	public function testDownloadsAuthenticatedThumbnailFromFixedMatrixHost(): void {
		$body = fopen('php://memory', 'r+');
		self::assertIsResource($body);
		fwrite($body, 'image-bytes');
		rewind($body);

		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getHeader')->willReturnCallback(static fn (string $name): string => match ($name) {
			'Content-Type' => 'image/webp; charset=binary',
			default => '',
		});
		$this->response->method('getBody')->willReturn($body);
		$this->httpClient
			->expects(self::once())
			->method('get')
			->with(
				self::callback(static function (string $url): bool {
					self::assertStringStartsWith(
						'https://chat.church.tools/_matrix/media/v3/thumbnail/example.org%3A8448/AbC_123',
						$url,
					);
					parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
					self::assertSame([
						'width' => '128',
						'height' => '128',
						'method' => 'crop',
						'animated' => 'false',
						'allow_redirect' => 'false',
					], $query);
					return true;
				}),
				self::callback(static function (array $options): bool {
					self::assertSame('Bearer secret-token', $options['headers']['Authorization']);
					self::assertFalse($options['allow_redirects']);
					self::assertTrue($options['stream']);
					return true;
				}),
			)
			->willReturn($this->response);

		$result = $this->matrix->thumbnail('secret-token', 'mxc://example.org:8448/AbC_123');

		self::assertSame('image-bytes', $result['body']);
		self::assertSame('image/webp', $result['contentType']);
		self::assertSame(hash('sha256', 'mxc://example.org:8448/AbC_123'), $result['etag']);
	}

	/** @dataProvider supportedContentTypes */
	public function testAcceptsSupportedImageContentTypes(string $contentType): void {
		$this->configureResponse(200, $contentType, 'image');
		$this->httpClient->method('get')->willReturn($this->response);

		self::assertSame($contentType, $this->matrix->thumbnail('token', 'mxc://example.org/media')['contentType']);
	}

	/** @return iterable<string,array{string}> */
	public static function supportedContentTypes(): iterable {
		foreach (['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'] as $contentType) {
			yield $contentType => [$contentType];
		}
	}

	/** @dataProvider invalidMxcUris */
	public function testRejectsInvalidMxcUriBeforeMakingARequest(string $mxcUri): void {
		$this->httpClient->expects(self::never())->method('get');

		$this->assertIntegrationError(
			fn (): array => $this->matrix->thumbnail('token', $mxcUri),
			'invalid_mxc_uri',
			400,
		);
	}

	/** @return iterable<string,array{string}> */
	public static function invalidMxcUris(): iterable {
		yield 'wrong scheme' => ['https://example.org/media'];
		yield 'missing media id' => ['mxc://example.org/'];
		yield 'path traversal' => ['mxc://example.org/../secret'];
		yield 'query string' => ['mxc://example.org/media?token=secret'];
		yield 'foreign path' => ['mxc://example.org/media/extra'];
	}

	public function testRejectsUnsupportedContentType(): void {
		$this->configureResponse(200, 'image/svg+xml', '<svg/>');
		$this->httpClient->method('get')->willReturn($this->response);

		$this->assertIntegrationError(
			fn (): array => $this->matrix->thumbnail('token', 'mxc://example.org/media'),
			'matrix_media_type_unsupported',
			415,
		);
	}

	public function testRejectsOversizedResponseFromContentLength(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getHeader')->willReturnCallback(static fn (string $name): string => match ($name) {
			'Content-Length' => (string)(5 * 1024 * 1024 + 1),
			'Content-Type' => 'image/png',
			default => '',
		});
		$this->response->expects(self::never())->method('getBody');
		$this->httpClient->method('get')->willReturn($this->response);

		$this->assertIntegrationError(
			fn (): array => $this->matrix->thumbnail('token', 'mxc://example.org/media'),
			'matrix_media_too_large',
			413,
		);
	}

	public function testRejectsOversizedStreamWithoutContentLength(): void {
		$body = fopen('php://memory', 'r+');
		self::assertIsResource($body);
		fwrite($body, str_repeat('x', 5 * 1024 * 1024 + 1));
		rewind($body);
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getHeader')->willReturnCallback(static fn (string $name): string => $name === 'Content-Type' ? 'image/png' : '');
		$this->response->method('getBody')->willReturn($body);
		$this->httpClient->method('get')->willReturn($this->response);

		$this->assertIntegrationError(
			fn (): array => $this->matrix->thumbnail('token', 'mxc://example.org/media'),
			'matrix_media_too_large',
			413,
		);
	}

	public function testMapsNetworkFailureToUnavailableError(): void {
		$this->httpClient->method('get')->willThrowException(new \RuntimeException('network unavailable'));

		$this->assertIntegrationError(
			fn (): array => $this->matrix->thumbnail('token', 'mxc://example.org/media'),
			'matrix_unavailable',
			502,
		);
	}

	public function testSearchesMessagesWithTheRoomFilter(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getBody')->willReturn(json_encode([
			'search_categories' => [
				'room_events' => [
					'results' => [[
						'result' => ['event_id' => '$event:example.org', 'type' => 'm.room.message'],
					]],
				],
			],
		], JSON_THROW_ON_ERROR));
		$this->httpClient
			->expects(self::once())
			->method('post')
			->with(
				'https://chat.church.tools/_matrix/client/v3/search',
				self::callback(static function (array $options): bool {
					self::assertSame('Bearer secret-token', $options['headers']['Authorization']);
					self::assertSame([
						'search_categories' => [
							'room_events' => [
								'search_term' => 'meeting notes',
								'keys' => ['content.body'],
								'filter' => ['rooms' => ['!room:example.org'], 'limit' => 20],
							],
						],
					], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));
					return true;
				}),
			)
			->willReturn($this->response);

		self::assertSame([
			['event_id' => '$event:example.org', 'type' => 'm.room.message'],
		], $this->matrix->searchRoomMessages('secret-token', '!room:example.org', 'meeting notes'));
	}

	public function testSearchesMessagesAcrossAccessibleRoomsWithoutARoomFilter(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getBody')->willReturn(json_encode([
			'search_categories' => ['room_events' => ['results' => [[
				'result' => ['event_id' => '$event:example.org', 'room_id' => '!room:example.org', 'type' => 'm.room.message'],
			]]]],
		], JSON_THROW_ON_ERROR));
		$this->httpClient
			->expects(self::once())
			->method('post')
			->with(
				'https://chat.church.tools/_matrix/client/v3/search',
				self::callback(static function (array $options): bool {
					self::assertSame([
						'search_categories' => ['room_events' => [
							'search_term' => 'meeting notes',
							'keys' => ['content.body'],
							'filter' => ['limit' => 20],
						]],
					], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));
					return true;
				}),
			)
			->willReturn($this->response);

		self::assertSame([[
			'roomId' => '!room:example.org',
			'event' => ['event_id' => '$event:example.org', 'room_id' => '!room:example.org', 'type' => 'm.room.message'],
		]], $this->matrix->searchMessages('secret-token', 'meeting notes'));
	}

	/** @dataProvider matrixFailureStatuses */
	public function testMapsMatrixHttpFailures(int $status, string $errorCode, int $httpStatus): void {
		$this->configureResponse($status, 'image/png', 'ignored');
		$this->httpClient->method('get')->willReturn($this->response);

		$this->assertIntegrationError(
			fn (): array => $this->matrix->thumbnail('token', 'mxc://example.org/media'),
			$errorCode,
			$httpStatus,
		);
	}

	/** @return iterable<string,array{int,string,int}> */
	public static function matrixFailureStatuses(): iterable {
		yield 'expired session' => [401, 'matrix_session_expired', 401];
		yield 'forbidden session' => [403, 'matrix_session_expired', 401];
		yield 'missing media' => [404, 'matrix_media_not_found', 404];
		yield 'redirect' => [302, 'matrix_media_failed', 502];
		yield 'upstream failure' => [500, 'matrix_media_failed', 502];
	}

	public function testMapsRateLimitWithRetryAfter(): void {
		$this->response->method('getStatusCode')->willReturn(429);
		$this->response->method('getHeader')->willReturnCallback(static fn (string $name): string => $name === 'Retry-After' ? '42' : '');
		$this->response->method('getBody')->willReturn('{}');
		$this->httpClient->method('get')->willReturn($this->response);

		try {
			$this->matrix->sync('token', null);
			self::fail('Expected an IntegrationException.');
		} catch (IntegrationException $exception) {
			self::assertSame('matrix_rate_limited', $exception->getErrorCode());
			self::assertSame(429, $exception->getHttpStatus());
			self::assertSame(42, $exception->getValue());
		}
	}

	public function testMapsRateLimitWithoutRetryAfterHeader(): void {
		$this->response->method('getStatusCode')->willReturn(429);
		$this->response->method('getHeader')->willReturn('');
		$this->response->method('getBody')->willReturn('{}');
		$this->httpClient->method('get')->willReturn($this->response);

		try {
			$this->matrix->sync('token', null);
			self::fail('Expected an IntegrationException.');
		} catch (IntegrationException $exception) {
			self::assertSame('matrix_rate_limited', $exception->getErrorCode());
			self::assertNull($exception->getValue());
		}
	}

	public function testSendsEditWithReplaceRelation(): void {
		$this->response->method('getStatusCode')->willReturn(200);
		$this->response->method('getBody')->willReturn(json_encode(['event_id' => '$edited:chat.church.tools']));
		$this->httpClient
			->expects(self::once())
			->method('put')
			->with(
				self::callback(static fn (string $url): bool => str_contains($url, '/send/m.room.message/nc-txn')),
				self::callback(static function (array $options): bool {
					$body = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
					self::assertSame('Bearer secret-token', $options['headers']['Authorization']);
					self::assertSame('new body', $body['body']);
					self::assertSame('new body', $body['new_content']['body']);
					self::assertSame('m.replace', $body['m.relates_to']['rel_type']);
					self::assertSame('$target:chat.church.tools', $body['m.relates_to']['event_id']);
					return true;
				}),
			)
			->willReturn($this->response);

		$result = $this->matrix->editMessage('secret-token', '!room:chat.church.tools', '$target:chat.church.tools', 'new body', 'nc-txn');

		self::assertSame('$edited:chat.church.tools', $result['event_id']);
	}

	private function configureResponse(int $status, string $contentType, string $body): void {
		$this->response->method('getStatusCode')->willReturn($status);
		$this->response->method('getHeader')->willReturnCallback(static fn (string $name): string => $name === 'Content-Type' ? $contentType : '');
		$this->response->method('getBody')->willReturn($body);
	}

	/** @param callable():array<string,mixed> $operation */
	private function assertIntegrationError(callable $operation, string $errorCode, int $httpStatus): void {
		try {
			$operation();
			self::fail('Expected an IntegrationException.');
		} catch (IntegrationException $exception) {
			self::assertSame($errorCode, $exception->getErrorCode());
			self::assertSame($httpStatus, $exception->getHttpStatus());
		}
	}
}
