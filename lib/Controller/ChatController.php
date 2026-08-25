<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\Service\ChatGateway;
use OCA\ChurchToolsChat\Service\UserContext;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

final class ChatController extends ApiController {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		private readonly UserContext $userContext,
		private readonly ChatGateway $gateway,
	) {
		parent::__construct($request, $logger);
	}

	#[NoAdminRequired]
	public function status(): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->getStatus($this->userContext->getUserId()));
	}

	#[NoAdminRequired]
	public function rooms(): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->getRooms($this->userContext->getUserId()));
	}

	#[NoAdminRequired]
	public function searchPersons(string $query): JSONResponse {
		return $this->respond(fn (): array => [
			'persons' => $this->gateway->searchPersons($this->userContext->getUserId(), $query),
		]);
	}

	#[NoAdminRequired]
	public function startDirect(int $personId): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->startDirectChat($this->userContext->getUserId(), $personId));
	}

	#[NoAdminRequired]
	public function messages(string $roomId, ?string $from = null, int $limit = 50): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->getMessages($this->userContext->getUserId(), $roomId, $from, $limit));
	}

	#[NoAdminRequired]
	public function message(string $roomId, string $eventId): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->getMessage($this->userContext->getUserId(), $roomId, $eventId));
	}

	#[NoAdminRequired]
	public function searchMessages(string $roomId, string $query, int $limit = 20): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->searchMessages($this->userContext->getUserId(), $roomId, $query, $limit));
	}

	#[NoAdminRequired]
	public function searchConversations(string $query, int $limit = 20): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->searchConversations($this->userContext->getUserId(), $query, $limit));
	}

	#[NoAdminRequired]
	public function details(string $roomId): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->getRoomDetails($this->userContext->getUserId(), $roomId));
	}

	#[NoAdminRequired]
	public function send(string $roomId, string $body, ?string $transactionId = null, ?string $replyTo = null): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->send($this->userContext->getUserId(), $roomId, $body, $transactionId, $replyTo), 201);
	}

	#[NoAdminRequired]
	public function react(string $roomId, string $eventId, string $emoji, ?string $transactionId = null): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->react($this->userContext->getUserId(), $roomId, $eventId, $emoji, $transactionId), 201);
	}

	#[NoAdminRequired]
	public function edit(string $roomId, string $eventId, string $body, ?string $transactionId = null): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->edit($this->userContext->getUserId(), $roomId, $eventId, $body, $transactionId), 201);
	}

	#[NoAdminRequired]
	public function redact(string $roomId, string $eventId): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->redact($this->userContext->getUserId(), $roomId, $eventId) ?? []);
	}

	#[NoAdminRequired]
	public function setFullyRead(string $roomId, string $eventId): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->setFullyRead($this->userContext->getUserId(), $roomId, $eventId) ?? []);
	}

	#[NoAdminRequired]
	public function typing(string $roomId, bool $typing): JSONResponse {
		$this->gateway->setTyping($this->userContext->getUserId(), $roomId, $typing);
		return new JSONResponse([]);
	}

	#[NoAdminRequired]
	public function sync(?string $since = null): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->sync($this->userContext->getUserId(), $since));
	}
}
