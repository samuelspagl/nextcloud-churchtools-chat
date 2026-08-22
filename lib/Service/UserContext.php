<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCP\IUserSession;

final class UserContext {
	public function __construct(private readonly IUserSession $userSession) {
	}

	public function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new IntegrationException('not_authenticated', 'Authentication is required.', 401);
		}

		return $user->getUID();
	}
}
