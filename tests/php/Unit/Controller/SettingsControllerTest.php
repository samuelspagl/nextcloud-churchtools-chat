<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Controller;

use OCA\ChurchToolsChat\Controller\SettingsController;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCA\ChurchToolsChat\Service\UserContext;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SettingsControllerTest extends TestCase {
	private SettingsController $controller;
	private AppConfigService $appConfig;
	private SecretService $secrets;
	private UserContext $userContext;

	protected function setUp(): void {
		parent::setUp();
		$user = $this->createMock(\OCP\IUser::class);
		$user->method('getUID')->willReturn('admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$this->userContext = new UserContext($userSession);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			static fn (string $userId, string $appId, string $key, string $default = ''): string => match ($key) {
				'churchtools_token' => 'encrypted-token',
				'churchtools_person_guid' => '2E53CF18-8F3B-45B4-95A9-DACFB27FF3DC',
				'matrix_access_token' => 'encrypted-matrix-token',
				'matrix_user_id' => '@ct_2e53cf18-8f3b-45b4-95a9-dacfb27ff3dc:chat.church.tools',
				default => $default,
			},
		);
		$crypto = $this->createMock(\OCP\Security\ICrypto::class);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $value): string => match ($value) {
			'encrypted-token' => 'token',
			'encrypted-matrix-token' => 'matrix-token',
			default => '',
		});
		$this->secrets = new SecretService($config, $crypto);

		$appConfig = $this->createMock(IConfig::class);
		$appConfig->method('getAppValue')->willReturn('');
		$this->appConfig = new AppConfigService($appConfig, new TenantUrlValidator());
	}

	private function buildController(): SettingsController {
		$clientService = $this->createMock(\OCP\Http\Client\IClientService::class);
		$clientService->method('newClient')->willReturn($this->createMock(\OCP\Http\Client\IClient::class));
		return new SettingsController(
			$this->createMock(IRequest::class),
			$this->createMock(LoggerInterface::class),
			$this->userContext,
			$this->appConfig,
			$this->secrets,
			new ChurchToolsClient($clientService),
			new MatrixClient($clientService, new MatrixUserId($this->appConfig), $this->appConfig),
		);
	}

	public function testGetMarksAsNotConfiguredWhenTenantMissing(): void {
		$response = $this->buildController()->get();

		$data = $response->getData()['data'];
		self::assertSame('', $data['tenantUrl']);
		self::assertFalse($data['configured']);
	}

	public function testSaveRequiresConfiguredServer(): void {
		$response = $this->buildController()->save('token', 'matrix-password');

		self::assertSame('server_not_configured', $response->getData()['error']['code']);
	}
}
