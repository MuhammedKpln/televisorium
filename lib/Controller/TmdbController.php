<?php

declare(strict_types=1);

namespace OCA\Televisorium\Controller;

use OCA\Televisorium\Exception\NoApiKeyException;
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
class TmdbController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private TmdbService $tmdbService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Search movies and tv shows on TMDb.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{query: string, results: list<array<string, mixed>>}, array{}>
	 *
	 * 200: Search results returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/search')]
	public function search(string $query): DataResponse {
		if (trim($query) === '') {
			return new DataResponse(['query' => $query, 'results' => []]);
		}

		try {
			return new DataResponse($this->tmdbService->search($query));
		} catch (NoApiKeyException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_UNAUTHORIZED);
		} catch (\Exception $e) {
			return new DataResponse(['message' => 'Search failed: ' . $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
	}

	/**
	 * Get details (and for tv, the seasons) of a title.
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Details returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/details/{itemType}/{tmdbId}')]
	public function details(string $itemType, int $tmdbId): DataResponse {
		if (!in_array($itemType, ['movie', 'tv'], true)) {
			return new DataResponse(['message' => 'Invalid type'], Http::STATUS_BAD_REQUEST);
		}

		try {
			return new DataResponse($this->tmdbService->details($itemType, $tmdbId));
		} catch (NoApiKeyException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_UNAUTHORIZED);
		} catch (\Exception $e) {
			return new DataResponse(['message' => 'Details failed: ' . $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
	}

	/**
	 * Get the episodes of one season of a tv show.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Episodes returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/season/{tmdbId}/{seasonNumber}')]
	public function season(int $tmdbId, int $seasonNumber): DataResponse {
		try {
			return new DataResponse($this->tmdbService->episodes($tmdbId, $seasonNumber));
		} catch (NoApiKeyException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_UNAUTHORIZED);
		} catch (\Exception $e) {
			return new DataResponse(['message' => 'Season fetch failed: ' . $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
	}
}
