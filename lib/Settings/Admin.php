<?php

declare(strict_types=1);

namespace OCA\Olvid\Settings;

use OCA\Olvid\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/**
 * Custom admin settings panel (Vue), shown in the Olvid admin section.
 */
class Admin implements ISettings {
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings-admin');
	}

	public function getSection(): string {
		return 'olvid';
	}

	public function getPriority(): int {
		return 5;
	}
}
