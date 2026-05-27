<?php

declare(strict_types=1);

namespace OCA\Olvid\Profile;

use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Profile\ILinkAction;

class OlvidProfileLinkAction implements ILinkAction {
	private bool $hasIdentity = false;

	public function __construct(
		private readonly OlvidUserConfigManager $userConfig,
		private readonly IURLGenerator          $urlGenerator,
	) {}

	public function preload(IUser $targetUser): void {
		$this->hasIdentity = $this->userConfig->hasIdentity($targetUser->getUID());
	}

	public function getAppId(): string {
		return Application::APP_ID;
	}

	public function getId(): string {
		return 'olvid-identity';
	}

	public function getDisplayId(): string {
		return 'Olvid';
	}

	public function getTitle(): string {
		return 'Connected with Olvid';
	}

	public function getPriority(): int {
		return 10;
	}

	public function getIcon(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'profile-link-action.svg')
		);
	}

	public function getTarget(): ?string {
		return $this->hasIdentity ? "" : null;
	}
}
