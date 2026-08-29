<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\Exception\IntegrationException;
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

	/** @param list<string>|null $mentions */
	#[NoAdminRequired]
	public function send(string $roomId, string $body, ?string $transactionId = null, ?string $replyTo = null, ?array $mentions = null): JSONResponse {
		return $this->respond(fn (): array => $this->gateway->send($this->userContext->getUserId(), $roomId, $body, $transactionId, $replyTo, $mentions), 201);
	}

	#[NoAdminRequired]
	public function sendAttachment(string $roomId, ?string $transactionId = null): JSONResponse {
		$file = $this->request->getUploadedFile('file');
		return $this->respond(function () use ($roomId, $transactionId, $file): array {
			$error = is_array($file) ? ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
			if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
				throw new IntegrationException('matrix_media_too_large', 'The attachment is too large.', 413);
			}
			if (!is_array($file) || $error !== UPLOAD_ERR_OK) {
				throw new IntegrationException('invalid_attachment', 'No file was uploaded.', 400);
			}
			$contents = file_get_contents((string)$file['tmp_name']);
			if ($contents === false) {
				throw new IntegrationException('invalid_attachment', 'The uploaded file could not be read.', 400);
			}
			$contentType = (string)($file['type'] ?? 'application/octet-stream');
			$filename = (string)($file['name'] ?? 'attachment');
			return $this->gateway->sendAttachment($this->userContext->getUserId(), $roomId, $contents, $contentType, $filename, $transactionId);
		}, 201);
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
