<?php

declare(strict_types=1);

namespace OCA\Televisorium\Controller;

use OCA\Televisorium\Service\SettingsService;
use OCA\Televisorium\Service\TmdbService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-suppress UnusedClass
 */
class SettingsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private SettingsService $settingsService,
		private TmdbService $tmdbService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get whether a TMDb API key is configured and the active language.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{configured: bool, language: string}, array{}>
	 *
	 * 200: Settings returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/settings')]
	public function index(): DataResponse {
		return new DataResponse([
			'configured' => $this->settingsService->hasApiKey(),
			'language' => $this->settingsService->getLanguage(),
		]);
	}

	/**
	 * Store (or validate) the personal TMDb API key and/or language.
	 *
	 * @param string|null $apiKey TMDb API key (v3 auth)
	 * @param string|null $language TMDb language tag (e.g. en-US)
	 * @return DataResponse<Http::STATUS_OK, array{configured: bool, language: string}, array{}>
	 *
	 * 200: Settings saved
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/settings')]
	public function set(?string $apiKey = null, ?string $language = null): DataResponse {
		if ($apiKey === null && $language === null) {
			return new DataResponse(['message' => 'Nothing to save'], Http::STATUS_BAD_REQUEST);
		}

		if ($apiKey !== null) {
			$apiKey = trim($apiKey);
			if ($apiKey === '') {
				return new DataResponse(['message' => 'API key required'], Http::STATUS_BAD_REQUEST);
			}

			if (!$this->tmdbService->validateApiKey($apiKey)) {
				return new DataResponse(['message' => 'Invalid TMDb API key'], Http::STATUS_BAD_REQUEST);
			}

			$this->settingsService->setPersonalApiKey($apiKey);
		}

		if ($language !== null) {
			$language = trim($language);
			if ($language !== '' && !$this->isValidLanguage($language)) {
				return new DataResponse(['message' => 'Invalid TMDb language'], Http::STATUS_BAD_REQUEST);
			}

			$this->settingsService->setPersonalLanguage($language);
		}

		return new DataResponse([
			'configured' => $this->settingsService->hasApiKey(),
			'language' => $this->settingsService->getLanguage(),
		]);
	}

	/**
	 * Clear the personal TMDb API key.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{configured: bool, language: string}, array{}>
	 *
	 * 200: Key removed
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/settings')]
	public function clear(): DataResponse {
		$this->settingsService->setPersonalApiKey('');
		return new DataResponse([
			'configured' => $this->settingsService->hasApiKey(),
			'language' => $this->settingsService->getLanguage(),
		]);
	}

	private function isValidLanguage(string $language): bool {
		return preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/i', $language) === 1;
	}
}
