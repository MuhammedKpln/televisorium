<?php

declare(strict_types=1);

namespace OCA\Televisorium\Service;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EpisodeService {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function getAllForItem(int $itemId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.*')
			->from('televisorium_episodes', 'e')
			->join('e', 'televisorium_items', 'i', $qb->expr()->eq('e.item_id', 'i.id'))
			->where($qb->expr()->eq('e.item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('i.user_id', $qb->createNamedParameter($userId)))
			->orderBy('e.season_number', 'ASC')
			->addOrderBy('e.episode_number', 'ASC');

		$result = $qb->executeQuery();
		$episodes = [];
		while (($row = $result->fetch()) !== false) {
			$episodes[] = $this->format($row);
		}
		$result->closeCursor();

		return $episodes;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get(int $id, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.*')
			->from('televisorium_episodes', 'e')
			->join('e', 'televisorium_items', 'i', $qb->expr()->eq('e.item_id', 'i.id'))
			->where($qb->expr()->eq('e.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('i.user_id', $qb->createNamedParameter($userId)));

		$row = $qb->executeQuery()->fetch();
		if ($row === false) {
			throw new DoesNotExistException('Episode not found');
		}
		return $this->format($row);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function create(int $itemId, string $userId, array $data): array {
		$season = (int)($data['season_number'] ?? 1);
		$number = (int)($data['episode_number'] ?? 1);
		if ($season < 1 || $number < 1) {
			throw new \InvalidArgumentException('Season and episode number must be >= 1');
		}

		return $this->upsert($itemId, $userId, $season, $number, $data);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function update(int $id, string $userId, array $data): array {
		$episode = $this->get($id, $userId);

		$watched = array_key_exists('watched', $data)
			? filter_var($data['watched'], FILTER_VALIDATE_BOOL)
			: $episode['watched'];
		$watchedSeconds = array_key_exists('watched_seconds', $data)
			? max(0, (int)$data['watched_seconds'])
			: (int)$episode['watched_seconds'];

		// Reaching the end of the runtime means the episode is watched
		if (!$watched && $episode['runtime'] !== null && $watchedSeconds >= (int)$episode['runtime'] * 60) {
			$watched = true;
			$watchedSeconds = 0;
		}

		// A watched episode has no remaining position
		if ($watched) {
			$watchedSeconds = 0;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('televisorium_episodes')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$changed = false;
		if (array_key_exists('title', $data)) {
			$qb->set('title', $qb->createNamedParameter($data['title'] !== null ? (string)$data['title'] : null));
			$changed = true;
		}
		if (array_key_exists('runtime', $data)) {
			$qb->set('runtime', $qb->createNamedParameter($data['runtime'] !== null ? (int)$data['runtime'] : null, IQueryBuilder::PARAM_INT));
			$changed = true;
		}
		if (array_key_exists('season_number', $data)) {
			$qb->set('season_number', $qb->createNamedParameter((int)$data['season_number'], IQueryBuilder::PARAM_INT));
			$changed = true;
		}
		if (array_key_exists('episode_number', $data)) {
			$qb->set('episode_number', $qb->createNamedParameter((int)$data['episode_number'], IQueryBuilder::PARAM_INT));
			$changed = true;
		}
		if ($watched !== $episode['watched'] || array_key_exists('watched', $data)) {
			$qb->set('watched', $qb->createNamedParameter((int)$watched, IQueryBuilder::PARAM_BOOL));
			$changed = true;
		}
		if ($watchedSeconds !== (int)$episode['watched_seconds'] || array_key_exists('watched_seconds', $data)) {
			$qb->set('watched_seconds', $qb->createNamedParameter($watchedSeconds, IQueryBuilder::PARAM_INT));
			$changed = true;
		}

		if ($changed) {
			$qb->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
			$qb->executeStatement();
		}

		$result = $this->get($id, $userId);
		$this->reconcileItemStatus((int)$result['item_id'], $userId);

		return $result;
	}

	public function delete(int $id, string $userId): void {
		$episode = $this->get($id, $userId);
		$itemId = (int)$episode['item_id'];

		$qb = $this->db->getQueryBuilder();
		$qb->delete('televisorium_episodes')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		$this->reconcileItemStatus($itemId, $userId);
	}

	public function deleteAllForItem(int $itemId, string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('televisorium_episodes')
			->where($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$ids = [];
		while (($id = $result->fetchOne()) !== false) {
			$ids[] = (int)$id;
		}
		$result->closeCursor();

		if (count($ids) === 0) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete('televisorium_episodes')
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->executeStatement();
	}

	/**
	 * @param list<array{season_number: int, episode_number: int, title: ?string, runtime: ?int}> $episodes
	 * @return list<array<string, mixed>>
	 */
	public function upsertMany(int $itemId, string $userId, array $episodes): array {
		$result = [];
		foreach ($episodes as $episode) {
			$result[] = $this->upsert(
				$itemId,
				$userId,
				(int)$episode['season_number'],
				(int)$episode['episode_number'],
				$episode,
			);
		}
		return $result;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function upsert(int $itemId, string $userId, int $season, int $number, array $data): array {
		$existing = $this->findBySeasonEpisode($itemId, $userId, $season, $number);

		if ($existing !== null) {
			$title = $existing['title'];
			if (array_key_exists('title', $data)) {
				$title = $data['title'] !== null ? (string)$data['title'] : null;
			}

			$runtime = $existing['runtime'];
			if (isset($data['runtime'])) {
				$runtime = (int)$data['runtime'];
			}

			$qb = $this->db->getQueryBuilder();
			$qb->update('televisorium_episodes')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($existing['id'], IQueryBuilder::PARAM_INT)));
			$qb->set('title', $qb->createNamedParameter($title));
			$qb->set('runtime', $qb->createNamedParameter($runtime, IQueryBuilder::PARAM_INT));
			$qb->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
			$qb->executeStatement();
			return $this->get((int)$existing['id'], $userId);
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('televisorium_episodes')
			->values([
				'item_id' => $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT),
				'season_number' => $qb->createNamedParameter($season, IQueryBuilder::PARAM_INT),
				'episode_number' => $qb->createNamedParameter($number, IQueryBuilder::PARAM_INT),
				'title' => $qb->createNamedParameter($data['title'] ?? null),
				'runtime' => $qb->createNamedParameter(isset($data['runtime']) ? (int)$data['runtime'] : null, IQueryBuilder::PARAM_INT),
				'watched' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_BOOL),
				'watched_seconds' => $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'updated_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();

		$result = $this->get((int)$qb->getLastInsertId(), $userId);
		$this->reconcileItemStatus($itemId, $userId);

		return $result;
	}

	/**
	 * Derive the parent tv show's status from its watched episodes.
	 *
	 * All episodes watched -> 'watched'; otherwise any watched or partial
	 * progress -> 'watching'. Never downgrades back to an explicit status.
	 */
	private function reconcileItemStatus(int $itemId, string $userId): void {
		$statusQb = $this->db->getQueryBuilder();
		$statusQb->select('status')
			->from('televisorium_items')
			->where($statusQb->expr()->eq('id', $statusQb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)))
			->andWhere($statusQb->expr()->eq('user_id', $statusQb->createNamedParameter($userId)));
		$result = $statusQb->executeQuery();
		$currentStatus = $result->fetchOne();
		$result->closeCursor();
		if ($currentStatus === false) {
			return;
		}

		$stats = $this->db->getQueryBuilder();
		$stats->selectAlias($stats->func()->count('id'), 'total')
			->selectAlias($stats->func()->sum('watched'), 'watched_count')
			->selectAlias($stats->func()->max('watched_seconds'), 'max_seconds')
			->from('televisorium_episodes')
			->where($stats->expr()->eq('item_id', $stats->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		$result = $stats->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if ($row === false) {
			return;
		}

		$total = (int)$row['total'];
		$watched = (int)$row['watched_count'];
		$hasProgress = (int)$row['max_seconds'] > 0;

		if ($total === 0) {
			return;
		}

		if ($watched === $total) {
			$newStatus = 'watched';
		} elseif ($watched > 0 || $hasProgress) {
			$newStatus = 'watching';
		} else {
			return;
		}

		if ($newStatus === $currentStatus) {
			return;
		}

		$update = $this->db->getQueryBuilder();
		$update->update('televisorium_items')
			->set('status', $update->createNamedParameter($newStatus))
			->set('updated_at', $update->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($update->expr()->eq('id', $update->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)));
		$update->executeStatement();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findBySeasonEpisode(int $itemId, string $userId, int $season, int $number): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('e.*')
			->from('televisorium_episodes', 'e')
			->join('e', 'televisorium_items', 'i', $qb->expr()->eq('e.item_id', 'i.id'))
			->where($qb->expr()->eq('e.item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('i.user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('e.season_number', $qb->createNamedParameter($season, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('e.episode_number', $qb->createNamedParameter($number, IQueryBuilder::PARAM_INT)));

		$row = $qb->executeQuery()->fetch();
		return $row === false ? null : $this->format($row);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format(array $row): array {
		return [
			'id' => (int)$row['id'],
			'item_id' => (int)$row['item_id'],
			'season_number' => (int)$row['season_number'],
			'episode_number' => (int)$row['episode_number'],
			'title' => $row['title'],
			'runtime' => $row['runtime'] !== null ? (int)$row['runtime'] : null,
			'watched' => (bool)$row['watched'],
			'watched_seconds' => (int)$row['watched_seconds'],
			'updated_at' => (int)$row['updated_at'],
		];
	}
}
