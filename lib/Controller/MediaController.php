<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Controller;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCA\ChurchToolsChat\Exception\IntegrationException;
use OCA\ChurchToolsChat\Service\MatrixClient;
use OCA\ChurchToolsChat\Service\SecretService;
use OCA\ChurchToolsChat\Service\UserContext;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

final class MediaController extends Controller {
	private const CACHE_SECONDS = 86400;
	private const IMAGE_CONTENT_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];

	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly UserContext $userContext,
		private readonly SecretService $secrets,
		private readonly MatrixClient $matrix,
		private readonly IRootFolder $rootFolder,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function thumbnail(string $mxc): Response {
		try {
			$media = $this->matrix->imageThumbnail($this->token(), $mxc);
			return $this->inline($media, 'matrix-image');
		} catch (IntegrationException $e) {
			return $this->mediaError('thumbnail', $e);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function download(string $mxc, string $filename = 'attachment'): Response {
		try {
			$media = $this->matrix->media($this->token(), $mxc);
			$response = new DataDownloadResponse($media['body'], $this->safeFilename($filename), $media['contentType']);
			$response->addHeader('Content-Disposition', 'attachment; filename="' . addcslashes($this->safeFilename($filename), "\\\"") . '"');
			$response->addHeader('X-Content-Type-Options', 'nosniff');
			$response->setETag($media['etag']);
			return $response;
		} catch (IntegrationException $e) {
			return $this->mediaError('download', $e);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function view(string $mxc): Response {
		try {
			$media = $this->matrix->media($this->token(), $mxc);
			if (!in_array($media['contentType'], self::IMAGE_CONTENT_TYPES, true)) {
				throw new IntegrationException('matrix_media_type_unsupported', 'The attachment is not an image.', 415);
			}
			return $this->inline($media, 'matrix-image');
		} catch (IntegrationException $e) {
			return $this->mediaError('view', $e);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function save(string $mxc, string $directory, string $filename = 'attachment'): JSONResponse {
		try {
			$userId = $this->userContext->getUserId();
			$folder = $this->folder($userId, $directory);
			if (!$folder->isCreatable()) {
				throw new IntegrationException('target_not_writable', 'The selected folder is not writable.', 403);
			}
			$media = $this->matrix->media($this->token(), $mxc);
			$name = $this->uniqueName($folder, $this->safeFilename($filename));
			$file = $folder->newFile($name);
			$file->putContent($media['body']);
			return new JSONResponse(['data' => ['path' => trim($directory, '/') . '/' . $name]], 201);
		} catch (IntegrationException $e) {
			return new JSONResponse(['error' => ['code' => $e->getErrorCode(), 'message' => $e->getMessage()]], $e->getHttpStatus());
		} catch (Throwable $e) {
			$this->logger->error('ChurchTools Chat attachment save failed', ['exceptionClass' => get_debug_type($e)]);
			return new JSONResponse(['error' => ['code' => 'attachment_save_failed', 'message' => 'The attachment could not be saved.']], 502);
		}
	}

	private function token(): string {
		$token = $this->secrets->getMatrixToken($this->userContext->getUserId());
		if ($token === '') throw new IntegrationException('matrix_not_connected', 'Connect CT Chat before loading Matrix attachments.', 409);
		return $token;
	}

	/** @param array{body:string,contentType:string,etag:string} $media */
	private function inline(array $media, string $filename): Response {
		$response = new DataDownloadResponse($media['body'], $filename, $media['contentType']);
		$response->addHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->setETag($media['etag']);
		$response->cacheFor(self::CACHE_SECONDS, false, true);
		return $response;
	}

	private function mediaError(string $operation, IntegrationException $e): Response {
		$this->logger->warning('ChurchTools Chat attachment ' . $operation . ' failed', ['errorCode' => $e->getErrorCode()]);
		$response = new Response($e->getHttpStatus());
		$response->addHeader('X-Content-Type-Options', 'nosniff');
		$response->cacheFor(0);
		return $response;
	}

	private function folder(string $userId, string $path): Folder {
		$path = trim($path, '/');
		if (strlen($path) > 4096 || str_contains($path, "\0") || preg_match('~(^|/)\.\.(/|$)~', $path) === 1) {
			throw new IntegrationException('invalid_target_directory', 'The selected folder is invalid.', 400);
		}
		try {
			$node = $path === '' ? $this->rootFolder->getUserFolder($userId) : $this->rootFolder->getUserFolder($userId)->get($path);
		} catch (NotFoundException) {
			throw new IntegrationException('target_not_found', 'The selected folder no longer exists.', 404);
		}
		if (!$node instanceof Folder) throw new IntegrationException('invalid_target_directory', 'The selected target is not a folder.', 400);
		return $node;
	}

	private function safeFilename(string $filename): string {
		$filename = trim(str_replace(['/', '\\', "\0"], '-', $filename));
		$filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
		return $filename !== '' ? mb_substr($filename, 0, 200) : 'attachment';
	}

	private function uniqueName(Folder $folder, string $filename): string {
		if (!$folder->nodeExists($filename)) return $filename;
		$extension = pathinfo($filename, PATHINFO_EXTENSION);
		$base = $extension === '' ? $filename : substr($filename, 0, -(strlen($extension) + 1));
		for ($number = 2; $number <= 1000; $number++) {
			$candidate = $base . ' (' . $number . ')' . ($extension === '' ? '' : '.' . $extension);
			if (!$folder->nodeExists($candidate)) return $candidate;
		}
		throw new IntegrationException('target_name_conflict', 'A unique filename could not be created.', 409);
	}
}
