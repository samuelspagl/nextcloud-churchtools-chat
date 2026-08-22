<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\TenantUrlValidator;
use PHPUnit\Framework\TestCase;

final class TenantUrlValidatorTest extends TestCase {
	public function testNormalizesHostedTenant(): void {
		$validator = new TenantUrlValidator();
		self::assertSame('https://efg-darmstadt.church.tools', $validator->normalize('https://EFG-Darmstadt.church.tools/'));
	}

	/** @dataProvider invalidUrlProvider */
	public function testRejectsUnsafeTenantUrls(string $url): void {
		$this->expectException(IntegrationException::class);
		(new TenantUrlValidator())->normalize($url);
	}

	/** @return iterable<string,array{string}> */
	public static function invalidUrlProvider(): iterable {
		yield 'http' => ['http://efg-darmstadt.church.tools'];
		yield 'foreign host' => ['https://example.org'];
		yield 'credentials' => ['https://user:password@efg-darmstadt.church.tools'];
		yield 'port' => ['https://efg-darmstadt.church.tools:8443'];
		yield 'path' => ['https://efg-darmstadt.church.tools/api'];
	}
}
