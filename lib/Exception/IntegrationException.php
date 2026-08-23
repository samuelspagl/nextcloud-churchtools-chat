<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Exception;

use RuntimeException;

	final class IntegrationException extends RuntimeException {
		public function __construct(
			private readonly string $errorCode,
			string $safeMessage,
			private readonly int $httpStatus = 400,
			private readonly ?int $value = null,
		) {
			parent::__construct($safeMessage);
		}

		public function getErrorCode(): string {
			return $this->errorCode;
		}

		public function getHttpStatus(): int {
			return $this->httpStatus;
		}

		/**
		 * Optional structured value attached to the error. For `matrix_rate_limited`
		 * this carries the `Retry-After` delay in seconds, if provided by Matrix.
		 */
		public function getValue(): ?int {
			return $this->value;
		}
	}
