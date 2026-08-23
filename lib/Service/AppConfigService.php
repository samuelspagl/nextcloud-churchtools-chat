<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCP\IConfig;

final class AppConfigService {
	private const CHURCHTOOLS_TENANT_URL = 'churchtools_tenant_url';
	private const MATRIX_SERVER_URL = 'matrix_server_url';
	private const DEFAULT_MATRIX_SERVER_URL = 'https://chat.church.tools';

	public function __construct(
		private readonly IConfig $config,
		private readonly TenantUrlValidator $tenantUrlValidator,
	) {
	}

	public function getTenantUrl(): string {
		return $this->config->getAppValue(Application::APP_ID, self::CHURCHTOOLS_TENANT_URL, '');
	}

	/** @throws IntegrationException */
	public function requireTenantUrl(): string {
		$tenantUrl = $this->getTenantUrl();
		if ($tenantUrl === '') {
			throw new IntegrationException(
				'server_not_configured',
				'The administrator has not configured the ChurchTools server yet. Ask your administrator to set the ChurchTools tenant URL in the app settings.',
				503,
			);
		}
		return $tenantUrl;
	}

	public function getMatrixServerUrl(): string {
		return $this->config->getAppValue(Application::APP_ID, self::MATRIX_SERVER_URL, self::DEFAULT_MATRIX_SERVER_URL);
	}

	public function getMatrixBaseUrl(): string {
		return rtrim($this->getMatrixServerUrl(), '/');
	}

	public function getMatrixServerName(): string {
		$host = parse_url($this->getMatrixServerUrl(), PHP_URL_HOST);
		return is_string($host) ? $host : '';
	}

	/** @return array{churchToolsTenantUrl:string,matrixServerUrl:string} */
	public function getState(): array {
		return [
			'churchToolsTenantUrl' => $this->getTenantUrl(),
			'matrixServerUrl' => $this->getMatrixServerUrl(),
		];
	}

	/** @throws IntegrationException */
	public function save(string $tenantUrl, string $matrixServerUrl): void {
		$tenantUrl = trim($tenantUrl);
		if ($tenantUrl === '') {
			throw new IntegrationException('missing_tenant_url', 'Enter the ChurchTools tenant URL.');
		}
		$tenantUrl = $this->tenantUrlValidator->normalize($tenantUrl);
		$matrixServerUrl = $this->normalizeMatrixServerUrl($matrixServerUrl);

		$this->config->setAppValue(Application::APP_ID, self::CHURCHTOOLS_TENANT_URL, $tenantUrl);
		$this->config->setAppValue(Application::APP_ID, self::MATRIX_SERVER_URL, $matrixServerUrl);
	}

	/** @throws IntegrationException */
	private function normalizeMatrixServerUrl(string $url): string {
		$url = trim($url);
		if ($url === '') {
			return self::DEFAULT_MATRIX_SERVER_URL;
		}

		$parts = parse_url($url);
		if (!is_array($parts)
			|| ($parts['scheme'] ?? '') !== 'https'
			|| !isset($parts['host'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
			|| isset($parts['port'])
			|| isset($parts['query'])
			|| isset($parts['fragment'])
			|| (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/')) {
			throw new IntegrationException('invalid_matrix_server_url', 'Use an HTTPS Matrix homeserver URL without path, credentials, port, query, or fragment.');
		}

		return 'https://' . strtolower(rtrim($parts['host'], '.'));
	}
}
