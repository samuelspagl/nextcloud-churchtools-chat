<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;

final class MatrixUserId {
	public function fromChurchToolsGuid(string $personGuid): string {
		$personGuid = strtolower(trim($personGuid));
		if (!preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $personGuid)) {
			throw new IntegrationException(
				'invalid_identity_response',
				'ChurchTools returned an invalid person GUID.',
				502,
			);
		}

		return '@ct_' . $personGuid . ':chat.church.tools';
	}
}
