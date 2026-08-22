<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Exception;

use RuntimeException;

final class IntegrationException extends RuntimeException {
	public function __construct(
		private readonly string $errorCode,
		string $safeMessage,
		private readonly int $httpStatus = 400,
	) {
		parent::__construct($safeMessage);
	}

	public function getErrorCode(): string {
		return $this->errorCode;
	}

	public function getHttpStatus(): int {
		return $this->httpStatus;
	}
}
