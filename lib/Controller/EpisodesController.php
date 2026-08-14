<?php

declare(strict_types=1);

namespace OCA\Televisorium\Controller;

use OCA\Televisorium\Service\EpisodeService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class EpisodesController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private EpisodeService $episodeService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all episodes of a tv show item.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Episodes returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/items/{itemId}/episodes')]
	public function index(int $itemId): DataResponse {
		return new DataResponse($this->episodeService->getAllForItem($itemId, $this->getUserId()));
	}

	/**
	 * Add an episode to a tv show item.
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Episode created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/items/{itemId}/episodes')]
	public function create(int $itemId): DataResponse {
		try {
			$episode = $this->episodeService->create($itemId, $this->getUserId(), $this->request->getParams());
			return new DataResponse($episode, Http::STATUS_CREATED);
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => 'Item not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Bulk-import episodes (e.g. from a TMDb season) into a tv show item.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Episodes imported
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/items/{itemId}/episodes/bulk')]
	public function bulkCreate(int $itemId): DataResponse {
		try {
			$episodes = $this->request->getParam('episodes', []);
			if (!is_array($episodes)) {
				throw new \InvalidArgumentException('episodes must be an array');
			}
			return new DataResponse($this->episodeService->upsertMany($itemId, $this->getUserId(), $episodes));
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => 'Item not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Update an episode (watched state, watched progress, ...).
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Episode updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/episodes/{id}')]
	public function update(int $id): DataResponse {
		try {
			return new DataResponse($this->episodeService->update($id, $this->getUserId(), $this->request->getParams()));
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => 'Episode not found'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Remove an episode.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: Episode deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/episodes/{id}')]
	public function destroy(int $id): DataResponse {
		try {
			$this->episodeService->delete($id, $this->getUserId());
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => 'Episode not found'], Http::STATUS_NOT_FOUND);
		}
	}

	private function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('No user session');
		}
		return $user->getUID();
	}
}
