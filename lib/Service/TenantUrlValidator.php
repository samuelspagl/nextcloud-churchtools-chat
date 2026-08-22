<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;

final class TenantUrlValidator {
	public function normalize(string $url): string {
		$url = trim($url);
		$parts = parse_url($url);
		if (!is_array($parts)
			|| ($parts['scheme'] ?? '') !== 'https'
			|| !isset($parts['host'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
			|| isset($parts['port'])
			|| isset($parts['query'])
			|| isset($parts['fragment'])) {
			throw new IntegrationException('invalid_tenant_url', 'Use an HTTPS ChurchTools tenant URL without path, credentials, port, query, or fragment.');
		}

		$host = strtolower(rtrim($parts['host'], '.'));
		if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?[.]church[.]tools$/', $host)) {
			throw new IntegrationException('tenant_not_allowed', 'Only hosted *.church.tools tenants are allowed.');
		}

		$path = $parts['path'] ?? '';
		if ($path !== '' && $path !== '/') {
			throw new IntegrationException('invalid_tenant_url', 'The ChurchTools tenant URL must not contain a path.');
		}

		return 'https://' . $host;
	}
}
