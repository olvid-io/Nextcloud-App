<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-adminSettings');
Util::addStyle(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-adminSettings');

?>

<div id="olvid-admin-settings"></div>
