<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\Service\AppConfigService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class AdminSettingsController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private readonly AppConfigService $appConfig,
	) {
		parent::__construct($request, $logger);
	}

	public function get(): JSONResponse {
		return $this->respond(fn (): array => $this->appConfig->getState());
	}

	public function save(string $churchToolsTenantUrl, string $matrixServerUrl): JSONResponse {
		return $this->respond(function () use ($churchToolsTenantUrl, $matrixServerUrl): array {
			$this->appConfig->save($churchToolsTenantUrl, $matrixServerUrl);
			return $this->appConfig->getState();
		});
	}
}
