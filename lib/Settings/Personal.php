<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Settings;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

final class Personal implements ISettings {
	public function getForm(): TemplateResponse {
		Util::addScript(Application::APP_ID, Application::APP_ID . '-settings');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-settings');
		return new TemplateResponse(Application::APP_ID, 'personal');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
