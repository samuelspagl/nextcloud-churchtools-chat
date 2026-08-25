<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\UserContext;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

final class AvatarController extends Controller {
	private const CACHE_SECONDS = 86400;

	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly UserContext $userContext,
		private readonly SecretService $secrets,
		private readonly MatrixClient $matrix,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function thumbnail(string $mxc): Response {
		try {
			$userId = $this->userContext->getUserId();
			$token = $this->secrets->getMatrixToken($userId);
			if ($token === '') {
				throw new IntegrationException('matrix_not_connected', 'Connect CT Chat before loading Matrix avatars.', 409);
			}

			$thumbnail = $this->matrix->thumbnail($token, $mxc);
			$response = new DataDownloadResponse(
				$thumbnail['body'],
				'matrix-avatar',
				$thumbnail['contentType'],
			);
			$response->addHeader('Content-Disposition', 'inline; filename="matrix-avatar"');
			$response->addHeader('X-Content-Type-Options', 'nosniff');
			$response->setETag($thumbnail['etag']);
			$response->cacheFor(self::CACHE_SECONDS, false, true);
			return $response;
		} catch (IntegrationException $e) {
			$this->logger->warning('ChurchTools Chat avatar request failed', ['errorCode' => $e->getErrorCode()]);
			return $this->errorResponse($e->getHttpStatus());
		} catch (Throwable $e) {
			$this->logger->error('Unexpected ChurchTools Chat avatar error', ['exceptionClass' => get_debug_type($e)]);
			return $this->errorResponse(500);
		}
	}

	private function errorResponse(int $status): Response {
		$response = new Response($status);
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->cacheFor(0);
		return $response;
	}
}
