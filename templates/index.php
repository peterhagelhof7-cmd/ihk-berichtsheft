<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Berichtsheft\AppInfo\Application::APP_ID, OCA\Berichtsheft\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Berichtsheft\AppInfo\Application::APP_ID, OCA\Berichtsheft\AppInfo\Application::APP_ID . '-main');

?>

<div id="berichtsheft"></div>
