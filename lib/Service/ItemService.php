<?php

declare(strict_types=1);

namespace OCA\Televisorium\Service;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ItemService {
	public const STATUS_WATCHLIST = 'watchlist';
	public const STATUS_WATCHING = 'watching';
	public const STATUS_WATCHED = 'watched';
	public const STATUS_ON_HOLD = 'on_hold';
	public const STATUS_DROPPED = 'dropped';

	public const TYPES = ['movie', 'tv'];
	public const STATUSES = [
		self::STATUS_WATCHLIST,
		self::STATUS_WATCHING,
		self::STATUS_WATCHED,
		self::STATUS_ON_HOLD,
		self::STATUS_DROPPED,
	];

	public function __construct(
		private IDBConnection $db,
		private EpisodeService $episodeService,
	) {
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getAll(string $userId, ?string $type = null, ?string $status = null, ?string $search = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('televisorium_items')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		if ($type !== null && $type !== '') {
			$qb->andWhere($qb->expr()->eq('item_type', $qb->createNamedParameter($type)));
		}
		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
		}
		if ($search !== null && $search !== '') {
			$qb->andWhere($qb->expr()->iLike('title', $qb->createNamedParameter('%' . $search . '%')));
		}

		$qb->orderBy('title', 'ASC');

		$rows = $this->fetchAll($qb);
		return array_map(fn (array $row) => $this->format($row), $rows);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get(int $id, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('televisorium_items')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			throw new DoesNotExistException('Item not found');
		}

		$item = $this->format($row);
		if ($item['item_type'] === 'tv') {
			$item['episodes'] = $this->episodeService->getAllForItem($id, $userId);
		}

		return $item;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function create(string $userId, array $data): array {
		$this->assertValid($data);

		$tmdbId = isset($data['tmdb_id']) ? (int)$data['tmdb_id'] : null;
		if ($tmdbId !== null && $this->existsWithTmdbId($userId, $tmdbId)) {
			throw new \RuntimeException('This title is already in your library');
		}

		$now = time();
		$qb = $this->db->getQueryBuilder();
		$qb->insert('televisorium_items')
			->values([
				'user_id' => $qb->createNamedParameter($userId),
				'item_type' => $qb->createNamedParameter($data['item_type']),
				'title' => $qb->createNamedParameter($data['title']),
				'tmdb_id' => $qb->createNamedParameter($tmdbId, IQueryBuilder::PARAM_INT),
				'year' => $qb->createNamedParameter(isset($data['year']) ? (int)$data['year'] : null, IQueryBuilder::PARAM_INT),
				'runtime' => $qb->createNamedParameter(isset($data['runtime']) ? (int)$data['runtime'] : null, IQueryBuilder::PARAM_INT),
				'poster_url' => $qb->createNamedParameter($data['poster_url'] ?? null),
				'backdrop_url' => $qb->createNamedParameter($data['backdrop_url'] ?? null),
				'overview' => $qb->createNamedParameter($data['overview'] ?? null),
				'status' => $qb->createNamedParameter($data['status'] ?? self::STATUS_WATCHLIST),
				'rating' => $qb->createNamedParameter(isset($data['rating']) ? (int)$data['rating'] : null, IQueryBuilder::PARAM_INT),
				'watched_seconds' => $qb->createNamedParameter((int)($data['watched_seconds'] ?? 0), IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
				'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();

		return $this->get((int)$qb->getLastInsertId(), $userId);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function update(int $id, string $userId, array $data): array {
		$current = $this->get($id, $userId);

		$allowed = ['item_type', 'title', 'year', 'runtime', 'poster_url', 'backdrop_url', 'overview', 'status', 'rating', 'watched_seconds'];
		$qb = $this->db->getQueryBuilder();
		$qb->update('televisorium_items')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$changed = false;
		$statusGiven = false;
		$watchedSeconds = null;

		foreach ($allowed as $field) {
			if (!array_key_exists($field, $data)) {
				continue;
			}
			$value = $data[$field];

			if ($field === 'status') {
				$statusGiven = true;
				$this->assertValidStatus($value);
				$qb->set('status', $qb->createNamedParameter((string)$value));
				$changed = true;
				continue;
			}
			if ($field === 'rating') {
				if ($value !== null) {
					$value = (int)$value;
					if ($value < 0 || $value > 10) {
						throw new \InvalidArgumentException('Rating must be between 0 and 10');
					}
				}
				if ($value === null) {
					$qb->set('rating', $qb->createNamedParameter(null));
				} else {
					$qb->set('rating', $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT));
				}
				$changed = true;
				continue;
			}
			if ($field === 'watched_seconds') {
				$watchedSeconds = max(0, (int)$value);
				continue;
			}

			if ($field === 'item_type' || $field === 'title') {
				$qb->set($field, $qb->createNamedParameter((string)$value));
			} elseif ($value === null) {
				$qb->set($field, $qb->createNamedParameter(null));
			} else {
				$qb->set($field, $qb->createNamedParameter((int)$value, IQueryBuilder::PARAM_INT));
			}
			$changed = true;
		}

		if ($watchedSeconds !== null) {
			$qb->set('watched_seconds', $qb->createNamedParameter($watchedSeconds, IQueryBuilder::PARAM_INT));
			$changed = true;

			// Without an explicit status, derive it from the progress
			if (!$statusGiven) {
				$runtimeSeconds = $current['runtime'] !== null ? (int)$current['runtime'] * 60 : null;
				$autoStatus = $runtimeSeconds !== null && $watchedSeconds >= $runtimeSeconds
					? self::STATUS_WATCHED
					: self::STATUS_WATCHING;
				if ($autoStatus !== $current['status']) {
					$qb->set('status', $qb->createNamedParameter($autoStatus));
					$changed = true;
				}
			}
		}

		if ($changed) {
			$qb->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
			$qb->executeStatement();
		}

		return $this->get($id, $userId);
	}

	public function delete(int $id, string $userId): void {
		$this->episodeService->deleteAllForItem($id, $userId);

		$qb = $this->db->getQueryBuilder();
		$qb->delete('televisorium_items')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		$qb->executeStatement();
	}

	private function existsWithTmdbId(string $userId, int $tmdbId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('televisorium_items')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('tmdb_id', $qb->createNamedParameter($tmdbId, IQueryBuilder::PARAM_INT)));
		return $qb->executeQuery()->fetch() !== false;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function assertValid(array $data): void {
		if (!isset($data['title']) || trim((string)$data['title']) === '') {
			throw new \InvalidArgumentException('Title is required');
		}
		if (!isset($data['item_type']) || !in_array($data['item_type'], self::TYPES, true)) {
			throw new \InvalidArgumentException('Item type must be "movie" or "tv"');
		}
		$this->assertValidStatus($data['status'] ?? self::STATUS_WATCHLIST);
	}

	private function assertValidStatus(mixed $status): void {
		if (!in_array($status, self::STATUSES, true)) {
			throw new \InvalidArgumentException('Invalid status');
		}
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function fetchAll(\OCP\DB\QueryBuilder\IQueryBuilder $qb): array {
		$result = $qb->executeQuery();
		$rows = [];
		while (($row = $result->fetch()) !== false) {
			$rows[] = $row;
		}
		$result->closeCursor();
		return $rows;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format(array $row): array {
		return [
			'id' => (int)$row['id'],
			'item_type' => $row['item_type'],
			'title' => $row['title'],
			'tmdb_id' => $row['tmdb_id'] !== null ? (int)$row['tmdb_id'] : null,
			'year' => $row['year'] !== null ? (int)$row['year'] : null,
			'runtime' => $row['runtime'] !== null ? (int)$row['runtime'] : null,
			'poster_url' => $row['poster_url'],
			'backdrop_url' => $row['backdrop_url'],
			'overview' => $row['overview'],
			'status' => $row['status'],
			'rating' => $row['rating'] !== null ? (int)$row['rating'] : null,
			'watched_seconds' => (int)$row['watched_seconds'],
			'created_at' => (int)$row['created_at'],
			'updated_at' => (int)$row['updated_at'],
		];
	}
}
