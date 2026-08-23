<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Controller;

use OCA\ChurchToolsChat\Controller\ApiController;
use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ApiControllerTest extends TestCase {
	private LoggerInterface $logger;
	private TestApiController $controller;

	protected function setUp(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
		$request = $this->createMock(IRequest::class);
		$this->controller = new TestApiController($request, $this->logger);
	}

	public function testForwardsValueInErrorEnvelope(): void {
		$response = $this->controller->run(fn () => throw new IntegrationException('matrix_rate_limited', 'rate limited', 429, 42));

		self::assertSame(429, $response->getStatus());
		$data = $response->getData();
		self::assertSame('matrix_rate_limited', $data['error']['code']);
		self::assertSame(42, $data['error']['value']);
	}

	public function testOmitsValueWhenAbsent(): void {
		$response = $this->controller->run(fn () => throw new IntegrationException('matrix_session_expired', 'expired', 401));

		self::assertSame(401, $response->getStatus());
		$data = $response->getData();
		self::assertArrayNotHasKey('value', $data['error']);
	}
}

final class TestApiController extends ApiController {
	public function run(callable $operation): JSONResponse {
		return $this->respond($operation);
	}
}
