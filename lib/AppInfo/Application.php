<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\AppInfo;

use OCA\ChurchToolsChat\Service\ChatGateway;
use OCA\ChurchToolsChat\Service\RoomDetailsProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

final class Application extends App implements IBootstrap {
	public const APP_ID = 'churchtools_chat';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias(RoomDetailsProvider::class, ChatGateway::class);
	}

	public function boot(IBootContext $context): void {
	}
}
