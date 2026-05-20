<?php

namespace OCA\Olvid\Settings;

use OCA\Olvid\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
		$this->l = $l;
		$this->urlGenerator = $urlGenerator;
	}

	public function getID() {
		return 'olvid'; //or a generic id if feasible
	}

	public function getName() {
		return "Olvid";
	}

	public function getPriority() {
		return 80;
	}

	public function getIcon() {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}
}
