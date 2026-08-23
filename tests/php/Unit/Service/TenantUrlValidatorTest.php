<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use PHPUnit\Framework\TestCase;

final class TenantUrlValidatorTest extends TestCase {
	public function testNormalizesHostedTenant(): void {
		$validator = new TenantUrlValidator();
		self::assertSame('https://example.church.tools', $validator->normalize('https://Example.church.tools/'));
	}

	/** @dataProvider invalidUrlProvider */
	public function testRejectsUnsafeTenantUrls(string $url): void {
		$this->expectException(IntegrationException::class);
		(new TenantUrlValidator())->normalize($url);
	}

	/** @return iterable<string,array{string}> */
	public static function invalidUrlProvider(): iterable {
		yield 'http' => ['http://example.church.tools'];
		yield 'foreign host' => ['https://example.org'];
		yield 'credentials' => ['https://user:password@example.church.tools'];
		yield 'port' => ['https://example.church.tools:8443'];
		yield 'path' => ['https://example.church.tools/api'];
	}
}
