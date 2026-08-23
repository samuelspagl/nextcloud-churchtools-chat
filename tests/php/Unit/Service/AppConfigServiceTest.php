<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

final class AppConfigServiceTest extends TestCase {
	/** @var array<string,string> */
	private array $values = [];

	private AppConfigService $appConfig;

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
		$this->appConfig = new AppConfigService($config, new TenantUrlValidator());
	}

	public function testTenantUrlIsEmptyByDefault(): void {
		self::assertSame('', $this->appConfig->getTenantUrl());
	}

	public function testMatrixServerDefaultsToChatChurchTools(): void {
		self::assertSame('https://chat.church.tools', $this->appConfig->getMatrixServerUrl());
		self::assertSame('https://chat.church.tools', $this->appConfig->getMatrixBaseUrl());
		self::assertSame('chat.church.tools', $this->appConfig->getMatrixServerName());
	}

	public function testSaveNormalizesTenantUrl(): void {
		$this->appConfig->save('https://EFG-Darmstadt.church.tools/', 'https://chat.church.tools/');

		self::assertSame('https://efg-darmstadt.church.tools', $this->appConfig->getTenantUrl());
		self::assertSame('https://chat.church.tools', $this->appConfig->getMatrixServerUrl());
	}

	public function testSaveStripsMatrixPathAndDefaultsWhenEmpty(): void {
		$this->appConfig->save('https://efg-darmstadt.church.tools', '');

		self::assertSame('https://chat.church.tools', $this->appConfig->getMatrixServerUrl());
	}

	public function testRequireTenantUrlThrowsWhenNotConfigured(): void {
		$this->expectException(IntegrationException::class);

		$this->appConfig->requireTenantUrl();
	}

	public function testSaveRejectsInvalidTenantUrl(): void {
		$this->expectException(IntegrationException::class);

		$this->appConfig->save('http://not-church.tools', 'https://chat.church.tools');
	}

	public function testSaveRejectsInvalidMatrixServerUrl(): void {
		$this->expectException(IntegrationException::class);

		$this->appConfig->save('https://efg-darmstadt.church.tools', 'http://example.org/path');
	}

	public function testStateReflectsSavedValues(): void {
		$this->appConfig->save('https://efg-darmstadt.church.tools', 'https://matrix.example.org');

		self::assertSame([
			'churchToolsTenantUrl' => 'https://efg-darmstadt.church.tools',
			'matrixServerUrl' => 'https://matrix.example.org',
		], $this->appConfig->getState());
	}
}
