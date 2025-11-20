<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-main');

?>

<div id="olvid" style="width: 100vw; min-height: 100vh;">
	<iframe style="width: 100%; height: 100%;" src="<?php p($_['configurationLink']) ?>" frameborder="0"></iframe>
</div>
