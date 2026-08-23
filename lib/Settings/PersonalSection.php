<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Settings;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

final class PersonalSection implements IIconSection {
	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('ChurchTools Chat');
	}

	public function getPriority(): int {
		return 50;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}
}
