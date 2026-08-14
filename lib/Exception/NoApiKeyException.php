<?php

declare(strict_types=1);

namespace OCA\Televisorium\Exception;

use Exception;

class NoApiKeyException extends Exception {
	public function __construct() {
		parent::__construct('No TMDb API key configured');
	}
}
