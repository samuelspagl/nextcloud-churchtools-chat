<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\AppConfigService;

final class MatrixUserId {
	public function __construct(
		private readonly AppConfigService $appConfig,
	) {
	}

	public function fromChurchToolsGuid(string $personGuid): string {
		$personGuid = strtolower(trim($personGuid));
		if (!preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $personGuid)) {
			throw new IntegrationException(
				'invalid_identity_response',
				'ChurchTools returned an invalid person GUID.',
				502,
			);
		}

		return '@ct_' . $personGuid . ':' . $this->appConfig->getMatrixServerName();
	}
}
