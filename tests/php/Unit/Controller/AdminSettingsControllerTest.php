<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Controller;

use OCA\ChurchToolsChat\Controller\AdminSettingsController;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AdminSettingsControllerTest extends TestCase {
	/** @var array<string,string> */
	private array $values = [];

	private AdminSettingsController $controller;

	protected function setUp(): void {
		parent::setUp();
		$values = &$this->values;
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static function (string $appId, string $key, string $default = '') use (&$values): string {
				return $values[$key] ?? $default;
			},
		);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $appId, string $key, string $value) use (&$values): void {
				$values[$key] = $value;
			},
		);
		$appConfig = new AppConfigService($config, new \OCA\ChurchToolsChat\Service\TenantUrlValidator());
		$this->controller = new AdminSettingsController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$appConfig,
		);
	}

	public function testGetReturnsDefaults(): void {
		$response = $this->controller->get();

		self::assertSame([
			'churchToolsTenantUrl' => '',
			'matrixServerUrl' => 'https://chat.church.tools',
		], $response->getData()['data']);
	}

	public function testSavePersistsConfiguration(): void {
		$response = $this->controller->save('https://example.church.tools', 'https://matrix.example.org');

		self::assertSame([
			'churchToolsTenantUrl' => 'https://example.church.tools',
			'matrixServerUrl' => 'https://matrix.example.org',
		], $response->getData()['data']);
	}

	public function testSaveRejectsInvalidMatrixServerUrl(): void {
		$response = $this->controller->save('https://example.church.tools', 'ftp://example.org');

		self::assertArrayHasKey('error', $response->getData());
		self::assertSame('invalid_matrix_server_url', $response->getData()['error']['code']);
	}
}
