<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\Olvid\AppInfo\Application::APP_ID, OCA\Olvid\AppInfo\Application::APP_ID . '-main');

?>

<div id="olvid" style="width: 100vw; min-height: 100vh;">
	<div style="margin-left: auto; margin-right: auto; width: fit-content; padding: 20px">
		<div style="width: fit-content; background-color: white; border-radius: 20px; padding: 20px">
			<a href="<?php p($_['configurationLink']) ?>" target="_blank" style="color: black;">
				Click me to see configuration link
			</a>
		</div>
	</div>

	<div style="margin-left: auto; margin-right: auto; width: fit-content; padding: 20px">
		<div style="width: fit-content; background-color: white; border-radius: 20px; padding: 20px">
			<a href="olvid://<?php p(substr($_['configurationLink'], strlen("https://"))) ?>" style="color: black;">
				Click me to configure Nextcloud in Olvid
			</a>
		</div>
	</div>
</div>
