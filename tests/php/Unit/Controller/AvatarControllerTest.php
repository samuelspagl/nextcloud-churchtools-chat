<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Controller;

use OCA\ChurchToolsChat\Controller\AvatarController;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCA\ChurchToolsChat\Service\UserContext;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AvatarControllerTest extends TestCase {
	public function testMissingMatrixSessionReturnsFallbackFriendlyError(): void {
		$request = $this->createMock(IRequest::class);
		$logger = $this->createMock(LoggerInterface::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('');
		$crypto = $this->createMock(ICrypto::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->expects(self::never())->method('newClient');
		$logger
			->expects(self::once())
			->method('warning')
			->with('ChurchTools Chat avatar request failed', ['errorCode' => 'matrix_not_connected']);

		$configApp = $this->createMock(\OCP\IConfig::class);
		$configApp->method('getAppValue')->willReturn('');
		$appConfig = new AppConfigService($configApp, new TenantUrlValidator());

		$controller = new AvatarController(
			$request,
			$logger,
			new UserContext($userSession),
			new SecretService($config, $crypto),
			new MatrixClient($clientService, new MatrixUserId($appConfig), $appConfig),
		);

		$response = $controller->thumbnail('mxc://chat.church.tools/avatar');

		self::assertSame(409, $response->getStatus());
		self::assertSame('', $response->render());
	}
}
