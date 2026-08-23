<?php

declare(strict_types=1);

namespace OCA\ChurchToolsChat\Service;

use OCA\ChurchToolsChat\AppInfo\Application;
use OCP\IConfig;
use OCP\Security\ICrypto;

final class SecretService {
	private const CT_TOKEN = 'churchtools_token';
	private const CT_PERSON_ID = 'churchtools_person_id';
	private const CT_PERSON_GUID = 'churchtools_person_guid';
	private const CT_DISPLAY_NAME = 'churchtools_display_name';
	private const CT_CAN_CHAT = 'churchtools_can_chat';
	private const MATRIX_TOKEN = 'matrix_access_token';
	private const MATRIX_USER_ID = 'matrix_user_id';
	private const MATRIX_DEVICE_ID = 'matrix_device_id';

	public function __construct(
		private readonly IConfig $config,
		private readonly ICrypto $crypto,
	) {
	}

	/** @param array{id:int,guid:string,displayName:string,canChat:bool} $identity */
	public function saveChurchTools(string $userId, string $token, array $identity): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::CT_TOKEN, $this->crypto->encrypt($token));
		$this->config->setUserValue($userId, Application::APP_ID, self::CT_PERSON_ID, (string)$identity['id']);
		$this->config->setUserValue($userId, Application::APP_ID, self::CT_PERSON_GUID, $identity['guid']);
		$this->config->setUserValue($userId, Application::APP_ID, self::CT_DISPLAY_NAME, $identity['displayName']);
		$this->config->setUserValue($userId, Application::APP_ID, self::CT_CAN_CHAT, $identity['canChat'] ? '1' : '0');
	}

	/** @param array{access_token:string,user_id:string,device_id?:string} $session */
	public function saveMatrixSession(string $userId, array $session): void {
		$this->config->setUserValue($userId, Application::APP_ID, self::MATRIX_TOKEN, $this->crypto->encrypt($session['access_token']));
		$this->config->setUserValue($userId, Application::APP_ID, self::MATRIX_USER_ID, $session['user_id']);
		$this->config->setUserValue($userId, Application::APP_ID, self::MATRIX_DEVICE_ID, $session['device_id'] ?? '');
	}

	public function clearMatrixSession(string $userId): void {
		foreach ([self::MATRIX_TOKEN, self::MATRIX_USER_ID, self::MATRIX_DEVICE_ID] as $key) {
			$this->config->deleteUserValue($userId, Application::APP_ID, $key);
		}
	}

	public function clearAll(string $userId): void {
		foreach ([self::CT_TOKEN, self::CT_PERSON_ID, self::CT_PERSON_GUID, self::CT_DISPLAY_NAME, self::CT_CAN_CHAT, self::MATRIX_TOKEN, self::MATRIX_USER_ID, self::MATRIX_DEVICE_ID] as $key) {
			$this->config->deleteUserValue($userId, Application::APP_ID, $key);
		}
	}

	/** @return array{configured:bool,personId:int|null,personGuid:string,displayName:string,canChat:bool|null,matrixConnected:bool,matrixUserId:string} */
	public function getPublicState(string $userId): array {
		$canChat = $this->getValue($userId, self::CT_CAN_CHAT);
		return [
			'configured' => $this->getValue($userId, self::CT_TOKEN) !== '',
			'personId' => ($value = $this->getValue($userId, self::CT_PERSON_ID)) !== '' ? (int)$value : null,
			'personGuid' => $this->getValue($userId, self::CT_PERSON_GUID),
			'displayName' => $this->getValue($userId, self::CT_DISPLAY_NAME),
			'canChat' => $canChat === '' ? null : $canChat === '1',
			'matrixConnected' => $this->getValue($userId, self::MATRIX_TOKEN) !== '',
			'matrixUserId' => $this->getValue($userId, self::MATRIX_USER_ID),
		];
	}

	public function getChurchToolsToken(string $userId): string {
		return $this->decryptValue($userId, self::CT_TOKEN);
	}

	public function getMatrixToken(string $userId): string {
		return $this->decryptValue($userId, self::MATRIX_TOKEN);
	}

	public function getMatrixUserId(string $userId): string {
		return $this->getValue($userId, self::MATRIX_USER_ID);
	}

	private function getValue(string $userId, string $key): string {
		return $this->config->getUserValue($userId, Application::APP_ID, $key, '');
	}

	private function decryptValue(string $userId, string $key): string {
		$value = $this->getValue($userId, $key);
		return $value === '' ? '' : $this->crypto->decrypt($value);
	}
}
