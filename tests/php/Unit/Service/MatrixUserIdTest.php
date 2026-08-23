<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

final class MatrixUserIdTest extends TestCase {
	private function subject(string $serverName): MatrixUserId {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $appId, string $key, string $default = ''): string => $key === 'matrix_server_url' ? $serverName : $default,
		);
		$appConfig = new AppConfigService($config, new TenantUrlValidator());
		return new MatrixUserId($appConfig);
	}

	public function testBuildsMatrixIdFromChurchToolsGuid(): void {
		self::assertSame(
			'@ct_2e53cf18-8f3b-45b4-95a9-dacfb27ff3dc:chat.church.tools',
			$this->subject('https://chat.church.tools')->fromChurchToolsGuid('2E53CF18-8F3B-45B4-95A9-DACFB27FF3DC'),
		);
	}

	public function testUsesConfiguredMatrixServerName(): void {
		self::assertSame(
			'@ct_2e53cf18-8f3b-45b4-95a9-dacfb27ff3dc:matrix.example.org',
			$this->subject('https://matrix.example.org')->fromChurchToolsGuid('2E53CF18-8F3B-45B4-95A9-DACFB27FF3DC'),
		);
	}

	public function testRejectsInvalidGuid(): void {
		$this->expectException(IntegrationException::class);

		$this->subject('https://chat.church.tools')->fromChurchToolsGuid('../unexpected');
	}
}
