<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\AppInfo;

use OCP\AppFramework\App;

final class Application extends App {
	public const APP_ID = 'churchtools_chat';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}
}
