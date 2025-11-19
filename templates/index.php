<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-main');

?>

<div id="olvid"></div>
