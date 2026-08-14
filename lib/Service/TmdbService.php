<?php

declare(strict_types=1);

namespace OCA\Televisorium\Service;

use GuzzleHttp\Exception\ClientException;
use OCA\Televisorium\Exception\NoApiKeyException;
use OCP\Http\Client\IClientService;

class TmdbService {
	private const API_BASE = 'https://api.themoviedb.org/3';
	private const IMAGE_BASE = 'https://image.tmdb.org/t/p/';

	/** @var array<string, array> request-scoped response cache */
	private array $cache = [];

	public function __construct(
		private SettingsService $settings,
		private IClientService $clientService,
	) {
	}

	public function isAvailable(): bool {
		return $this->settings->hasApiKey();
	}

	/**
	 * @return array{
	 *   query: string,
	 *   results: list<array{
	 *     tmdb_id: int,
	 *     item_type: string,
	 *     title: string,
	 *     year: ?int,
	 *     overview: ?string,
	 *     poster_url: ?string,
	 *     backdrop_url: ?string,
	 *     runtime: ?int,
	 *   }>
	 * }
	 */
	public function search(string $query): array {
		$data = $this->get('/search/multi', [
			'query' => $query,
			'include_adult' => 'false',
			'page' => 1,
		], 'search' . $query);

		$results = [];
		foreach (($data['results'] ?? []) as $result) {
			if (!in_array($result['media_type'] ?? '', ['movie', 'tv'], true)) {
				continue;
			}
			$results[] = $this->normalize($result, $result['media_type']);
		}

		return [
			'query' => $query,
			'results' => $results,
		];
	}

	/**
	 * @return array{movie: array, tv: array} normalized details
	 */
	public function details(string $itemType, int $tmdbId): array {
		if (!in_array($itemType, ['movie', 'tv'], true)) {
			throw new \InvalidArgumentException('Unsupported item type');
		}

		$data = $this->get('/' . $itemType . '/' . $tmdbId, ['append_to_response' => 'videos'], 'details' . $itemType . $tmdbId);
		return $this->normalize($data, $itemType);
	}

	/**
	 * Fetch the episodes of one season of a tv show.
	 *
	 * @return list<array{season_number: int, episode_number: int, title: ?string, runtime: ?int, tmdb_id: int}>
	 */
	public function episodes(int $tmdbId, int $seasonNumber): array {
		$data = $this->get('/tv/' . $tmdbId . '/season/' . $seasonNumber, [], 'season' . $tmdbId . $seasonNumber);

		$episodes = [];
		foreach (($data['episodes'] ?? []) as $episode) {
			$episodes[] = [
				'season_number' => (int)$episode['season_number'],
				'episode_number' => (int)$episode['episode_number'],
				'title' => $episode['name'] ?? null,
				'runtime' => isset($episode['runtime']) ? (int)$episode['runtime'] : null,
				'tmdb_id' => (int)$episode['id'],
			];
		}

		return $episodes;
	}

	public function validateApiKey(string $apiKey): bool {
		$client = $this->clientService->newClient();
		try {
			$response = $client->get(self::API_BASE . '/configuration', [
				'query' => ['api_key' => trim($apiKey)],
				'timeout' => 10,
			]);
			$data = json_decode($response->getBody(), true);
			return is_array($data) && isset($data['images']);
		} catch (ClientException $e) {
			return false;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get(string $path, array $query, string $cacheKey): array {
		$apiKey = $this->settings->getApiKey();
		if ($apiKey === null) {
			throw new NoApiKeyException();
		}

		$language = $this->settings->getLanguage();
		$cacheKey .= ':' . $language;

		if (isset($this->cache[$cacheKey])) {
			return $this->cache[$cacheKey];
		}

		$query['api_key'] = $apiKey;
		$query['language'] = $language;
		$client = $this->clientService->newClient();
		$response = $client->get(self::API_BASE . $path, [
			'query' => $query,
			'timeout' => 15,
		]);

		$data = json_decode($response->getBody(), true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid response from TMDb');
		}

		$this->cache[$cacheKey] = $data;
		return $data;
	}

	/**
	 * @param array<string, mixed> $result
	 * @return array<string, mixed>
	 */
	private function normalize(array $result, string $mediaType): array {
		$titleKey = $mediaType === 'tv' ? 'name' : 'title';
		$dateKey = $mediaType === 'tv' ? 'first_air_date' : 'release_date';

		$runtime = null;
		if ($mediaType === 'movie' && isset($result['runtime'])) {
			$runtime = (int)$result['runtime'];
		} elseif ($mediaType === 'tv') {
			$runtimes = $result['episode_run_time'] ?? [];
			if (is_array($runtimes) && count($runtimes) > 0) {
				$runtime = (int)$runtimes[0];
			}
		}

		return [
			'tmdb_id' => (int)$result['id'],
			'item_type' => $mediaType,
			'title' => (string)($result[$titleKey] ?? ''),
			'year' => isset($result[$dateKey]) && $result[$dateKey] !== '' ? (int)date('Y', strtotime($result[$dateKey])) : null,
			'overview' => isset($result['overview']) && $result['overview'] !== '' ? $result['overview'] : null,
			'poster_url' => isset($result['poster_path']) && $result['poster_path'] !== null
				? self::IMAGE_BASE . 'w500' . $result['poster_path']
				: null,
			'backdrop_url' => isset($result['backdrop_path']) && $result['backdrop_path'] !== null
				? self::IMAGE_BASE . 'w780' . $result['backdrop_path']
				: null,
			'runtime' => $runtime,
			'seasons' => $mediaType === 'tv' ? $this->extractSeasons($result) : null,
		];
	}

	/**
	 * @param array<string, mixed> $result
	 * @return list<array{season_number: int, episode_count: int}>
	 */
	private function extractSeasons(array $result): array {
		$seasons = [];
		foreach (($result['seasons'] ?? []) as $season) {
			if (($season['season_number'] ?? 0) === 0) {
				continue;
			}
			$seasons[] = [
				'season_number' => (int)$season['season_number'],
				'episode_count' => (int)($season['episode_count'] ?? 0),
			];
		}
		return $seasons;
	}
}
