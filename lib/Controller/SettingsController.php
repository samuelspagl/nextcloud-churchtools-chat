<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use OCA\ChurchToolsChat\Service\UserContext;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class SettingsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private readonly UserContext $userContext,
		private readonly TenantUrlValidator $urlValidator,
		private readonly SecretService $secrets,
		private readonly ChurchToolsClient $churchTools,
		private readonly MatrixClient $matrix,
	) {
		parent::__construct($request, $logger);
	}

	/** @NoAdminRequired */
	public function get(): JSONResponse {
		return $this->respond(fn (): array => $this->secrets->getPublicState($this->userContext->getUserId()));
	}

	/** @NoAdminRequired */
	public function save(string $tenantUrl, string $token, ?string $matrixPassword = null): JSONResponse {
		return $this->respond(function () use ($tenantUrl, $token, $matrixPassword): array {
			$userId = $this->userContext->getUserId();
			$tenantUrl = $this->urlValidator->normalize($tenantUrl);
			$previous = $this->secrets->getPublicState($userId);
			$token = trim($token);
			if ($token === '') {
				if (!$previous['configured'] || $previous['tenantUrl'] !== $tenantUrl) {
					throw new IntegrationException('missing_token', 'Enter a ChurchTools access token.');
				}
				$token = $this->secrets->getChurchToolsToken($userId);
			}

			$matrixPassword = (string)$matrixPassword;

			$identity = $this->churchTools->validateIdentity($tenantUrl, $token);
			$preserveMatrixSession = $matrixPassword === ''
				&& $previous['matrixConnected']
				&& $previous['tenantUrl'] === $tenantUrl
				&& strtolower($previous['personGuid']) === strtolower($identity['guid']);
			$this->secrets->saveChurchTools($userId, $tenantUrl, $token, $identity);
			if (!$preserveMatrixSession) {
				$this->secrets->clearMatrixSession($userId);
			}

			$bootstrapError = null;
			if ($preserveMatrixSession) {
				$bootstrapError = null;
			} elseif ($matrixPassword !== '') {
				try {
					$this->secrets->saveMatrixSession($userId, $this->matrix->bootstrap($identity['guid'], $matrixPassword));
				} catch (IntegrationException $e) {
					$bootstrapError = ['code' => $e->getErrorCode(), 'message' => $e->getMessage()];
				}
			} else {
				$bootstrapError = ['code' => 'matrix_credentials_required', 'message' => 'Enter the CT Chat password to connect the Matrix message transport.'];
			}

			return [
				...$this->secrets->getPublicState($userId),
				'bootstrapError' => $bootstrapError,
			];
		});
	}

	/** @NoAdminRequired */
	public function destroy(): JSONResponse {
		return $this->respond(function (): array {
			$this->secrets->clearAll($this->userContext->getUserId());
			return ['configured' => false];
		});
	}
}
