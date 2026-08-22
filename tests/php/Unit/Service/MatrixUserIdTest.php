<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Service;

use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\MatrixUserId;
use PHPUnit\Framework\TestCase;

final class MatrixUserIdTest extends TestCase {
	public function testBuildsMatrixIdFromChurchToolsGuid(): void {
		$subject = new MatrixUserId();

		self::assertSame(
			'@ct_2e53cf18-8f3b-45b4-95a9-dacfb27ff3dc:chat.church.tools',
			$subject->fromChurchToolsGuid('2E53CF18-8F3B-45B4-95A9-DACFB27FF3DC'),
		);
	}

	public function testRejectsInvalidGuid(): void {
		$this->expectException(IntegrationException::class);

		(new MatrixUserId())->fromChurchToolsGuid('../unexpected');
	}
}
