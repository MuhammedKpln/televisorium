<?php

declare(strict_types=1);

namespace OCA\Televisorium\Controller;

use OCA\Televisorium\Service\ItemService;
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
class ItemsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private ItemService $itemService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List all library items.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<array<string, mixed>>, array{}>
	 *
	 * 200: Items returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/items')]
	public function index(?string $type = null, ?string $status = null, ?string $search = null): DataResponse {
		$userId = $this->getUserId();
		if ($type !== null && !in_array($type, ItemService::TYPES, true)) {
			return new DataResponse(['message' => 'Invalid type'], Http::STATUS_BAD_REQUEST);
		}
		if ($status !== null && !in_array($status, ItemService::STATUSES, true)) {
			return new DataResponse(['message' => 'Invalid status'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->itemService->getAll($userId, $type, $status, $search));
	}

	/**
	 * Add a title to the library.
	 *
	 * @return DataResponse<Http::STATUS_CREATED, array<string, mixed>, array{}>
	 *
	 * 201: Item created
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/items')]
	public function create(): DataResponse {
		$userId = $this->getUserId();
		try {
			$item = $this->itemService->create($userId, $this->request->getParams());
			return new DataResponse($item, Http::STATUS_CREATED);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\RuntimeException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
		}
	}

	/**
	 * Get a single item, including episodes for tv shows.
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Item returned
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/items/{id}')]
	public function show(int $id): DataResponse {
		$userId = $this->getUserId();
		try {
			return new DataResponse($this->itemService->get($id, $userId));
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => 'Item not found'], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Update an item (status, rating, watched progress, ...).
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, mixed>, array{}>
	 *
	 * 200: Item updated
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/items/{id}')]
	public function update(int $id): DataResponse {
		$userId = $this->getUserId();
		try {
			return new DataResponse($this->itemService->update($id, $userId, $this->request->getParams()));
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => 'Item not found'], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Remove an item.
	 *
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 *
	 * 200: Item deleted
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/items/{id}')]
	public function destroy(int $id): DataResponse {
		$userId = $this->getUserId();
		try {
			$this->itemService->delete($id, $userId);
			return new DataResponse([]);
		} catch (DoesNotExistException) {
			return new DataResponse(['message' => 'Item not found'], Http::STATUS_NOT_FOUND);
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
