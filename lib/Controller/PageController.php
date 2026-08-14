<?php

declare(strict_types=1);

namespace OCA\Televisorium\Controller;

use OCA\Televisorium\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$response = new TemplateResponse(
			Application::APP_ID,
			'index',
		);

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('https://image.tmdb.org');
		$response->setContentSecurityPolicy($csp);

		return $response;
	}
}
