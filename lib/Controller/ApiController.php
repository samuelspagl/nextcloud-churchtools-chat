<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class ApiController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/** @param callable():array<string,mixed> $operation */
	protected function respond(callable $operation, int $successStatus = 200): JSONResponse {
		try {
			return new JSONResponse(['data' => $operation()], $successStatus);
		} catch (IntegrationException $e) {
			$this->logger->warning('ChurchTools Chat integration request failed', ['errorCode' => $e->getErrorCode()]);
			return new JSONResponse([
				'error' => ['code' => $e->getErrorCode(), 'message' => $e->getMessage()],
			], $e->getHttpStatus());
		} catch (Throwable $e) {
			$this->logger->error('Unexpected ChurchTools Chat error', ['exceptionClass' => get_debug_type($e)]);
			return new JSONResponse([
				'error' => ['code' => 'internal_error', 'message' => 'The request could not be completed.'],
			], 500);
		}
	}
}
