<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Controller;

use OCA\ChurchToolsChat\Controller\AdminSettingsController;
use OCA\ChurchToolsChat\Controller\AvatarController;
use OCA\ChurchToolsChat\Controller\ChatController;
use OCA\ChurchToolsChat\Controller\MediaController;
use OCA\ChurchToolsChat\Controller\PageController;
use OCA\ChurchToolsChat\Controller\SettingsController;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ControllerAccessTest extends TestCase {
	public function testUserRoutesAllowNonAdminUsers(): void {
		$routes = [
			SettingsController::class => ['get', 'save', 'destroy'],
			ChatController::class => [
				'status',
				'rooms',
				'searchPersons',
				'startDirect',
				'messages',
				'message',
				'searchMessages',
				'searchConversations',
				'details',
				'send',
				'react',
				'edit',
				'redact',
				'setFullyRead',
				'typing',
				'sync',
			],
			PageController::class => ['index'],
			AvatarController::class => ['thumbnail'],
			MediaController::class => ['thumbnail', 'download', 'view', 'save'],
		];

		foreach ($routes as $controller => $methods) {
			foreach ($methods as $method) {
				self::assertNotEmpty(
					(new ReflectionMethod($controller, $method))->getAttributes(NoAdminRequired::class),
					$controller . '::' . $method . ' must allow logged-in non-admin users.',
				);
			}
		}
	}

	public function testAdminSettingsRemainAdminOnly(): void {
		foreach (['get', 'save'] as $method) {
			self::assertSame(
				[],
				(new ReflectionMethod(AdminSettingsController::class, $method))->getAttributes(NoAdminRequired::class),
				AdminSettingsController::class . '::' . $method . ' must remain admin-only.',
			);
		}
	}

	public function testExistingCsrfExemptionsUseAttributes(): void {
		$routes = [
			PageController::class => ['index'],
			AvatarController::class => ['thumbnail'],
			MediaController::class => ['thumbnail', 'download', 'view', 'save'],
		];

		foreach ($routes as $controller => $methods) {
			foreach ($methods as $method) {
				self::assertNotEmpty(
					(new ReflectionMethod($controller, $method))->getAttributes(NoCSRFRequired::class),
					$controller . '::' . $method . ' must preserve its CSRF exemption.',
				);
			}
		}
	}
}
