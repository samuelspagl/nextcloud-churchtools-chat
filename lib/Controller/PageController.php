<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\Util;

final class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$this->eventDispatcher->dispatchTyped(new RenderReferenceEvent());
		Util::addScript(Application::APP_ID, Application::APP_ID . '-main');
		Util::addStyle(Application::APP_ID, Application::APP_ID . '-main');
		return new TemplateResponse(Application::APP_ID, 'main');
	}
}
