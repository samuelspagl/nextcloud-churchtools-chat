<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Tests\Unit\Controller;

use OCA\ChurchToolsChat\Controller\PageController;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PageControllerTest extends TestCase {
	public function testDispatchesReferenceAssetsBeforeRenderingPage(): void {
		$request = $this->createMock(IRequest::class);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatched = new RuntimeException('reference event dispatched');
		$eventDispatcher
			->expects(self::once())
			->method('dispatchTyped')
			->with(self::isInstanceOf(RenderReferenceEvent::class))
			->willThrowException($eventDispatched);

		$this->expectExceptionObject($eventDispatched);
		(new PageController($request, $eventDispatcher))->index();
	}
}
