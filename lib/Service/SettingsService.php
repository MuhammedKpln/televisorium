<?php

declare(strict_types=1);

namespace OCA\Televisorium\Service;

use OCA\Televisorium\AppInfo\Application;
use OCP\IConfig;
use OCP\IUserSession;

class SettingsService {
	public function __construct(
		private IConfig $config,
		private IUserSession $userSession,
	) {
	}

	public function getApiKey(): ?string {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$personal = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'tmdb_api_key', '');
			if ($personal !== '') {
				return $personal;
			}
		}

		$system = $this->config->getAppValue(Application::APP_ID, 'tmdb_api_key', '');
		return $system !== '' ? $system : null;
	}

	public function hasApiKey(): bool {
		return $this->getApiKey() !== null;
	}

	public function getLanguage(): string {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			$personal = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'tmdb_language', '');
			if ($personal !== '') {
				return $personal;
			}
		}

		$system = $this->config->getAppValue(Application::APP_ID, 'tmdb_language', 'en-US');
		return $system !== '' ? $system : 'en-US';
	}

	public function setPersonalLanguage(string $language): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		if ($language === '') {
			$this->config->deleteUserValue($user->getUID(), Application::APP_ID, 'tmdb_language');
			return;
		}

		$this->config->setUserValue($user->getUID(), Application::APP_ID, 'tmdb_language', trim($language));
	}

	public function setPersonalApiKey(string $apiKey): void {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return;
		}

		if ($apiKey === '') {
			$this->config->deleteUserValue($user->getUID(), Application::APP_ID, 'tmdb_api_key');
			return;
		}

		$this->config->setUserValue($user->getUID(), Application::APP_ID, 'tmdb_api_key', trim($apiKey));
	}
}
