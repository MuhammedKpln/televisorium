<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Televisorium\AppInfo\Application::APP_ID, OCA\Televisorium\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Televisorium\AppInfo\Application::APP_ID, OCA\Televisorium\AppInfo\Application::APP_ID . '-main');

?>

<div id="televisorium"></div>
