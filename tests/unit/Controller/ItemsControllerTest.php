<?php

declare(strict_types=1);

namespace Controller;

use OCA\Televisorium\Controller\ItemsController;
use OCA\Televisorium\Service\ItemService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class ItemsControllerTest extends TestCase {
	private function userSession(string $uid): IUserSession {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		return $session;
	}

	public function testIndexReturnsItems(): void {
		$itemService = $this->createMock(ItemService::class);
		$itemService->expects($this->once())
			->method('getAll')
			->with('user1', 'movie', null, null)
			->willReturn([]);

		$controller = new ItemsController(
			'televisorium',
			$this->createMock(IRequest::class),
			$itemService,
			$this->userSession('user1'),
		);

		$response = $controller->index('movie');
		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertEquals([], $response->getData());
	}

	public function testIndexRejectsInvalidType(): void {
		$controller = new ItemsController(
			'televisorium',
			$this->createMock(IRequest::class),
			$this->createMock(ItemService::class),
			$this->userSession('user1'),
		);

		$response = $controller->index('bogus');
		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}
}
