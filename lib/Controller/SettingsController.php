<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\AppConfigService;
use OCA\ChurchToolsChat\Service\ChurchToolsClient;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\UserContext;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class SettingsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private readonly UserContext $userContext,
		private readonly AppConfigService $appConfig,
		private readonly SecretService $secrets,
		private readonly ChurchToolsClient $churchTools,
		private readonly MatrixClient $matrix,
	) {
		parent::__construct($request, $logger);
	}

	/** @NoAdminRequired */
	public function get(): JSONResponse {
		return $this->respond(function (): array {
			$userId = $this->userContext->getUserId();
			$state = $this->secrets->getPublicState($userId);
			$tenantUrl = $this->appConfig->getTenantUrl();
			$state['tenantUrl'] = $tenantUrl;
			$state['configured'] = $state['configured'] && $tenantUrl !== '';
			return $state;
		});
	}

	/** @NoAdminRequired */
	public function save(string $token, ?string $matrixPassword = null): JSONResponse {
		return $this->respond(function () use ($token, $matrixPassword): array {
			$userId = $this->userContext->getUserId();
			$tenantUrl = $this->appConfig->requireTenantUrl();
			$previous = $this->secrets->getPublicState($userId);
			$token = trim($token);
			if ($token === '') {
				if (!$previous['configured']) {
					throw new IntegrationException('missing_token', 'Enter a ChurchTools access token.');
				}
				$token = $this->secrets->getChurchToolsToken($userId);
			}

			$matrixPassword = (string)$matrixPassword;

			$identity = $this->churchTools->validateIdentity($tenantUrl, $token);
			$previousServerName = $previous['matrixUserId'] !== ''
				? substr((string)strstr($previous['matrixUserId'], ':'), 1)
				: '';
			$preserveMatrixSession = $matrixPassword === ''
				&& $previous['matrixConnected']
				&& strtolower($previous['personGuid']) === strtolower($identity['guid'])
				&& $previousServerName === $this->appConfig->getMatrixServerName();
			$this->secrets->saveChurchTools($userId, $token, $identity);
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
				'tenantUrl' => $tenantUrl,
				'configured' => $previous['configured'] && $tenantUrl !== '',
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
