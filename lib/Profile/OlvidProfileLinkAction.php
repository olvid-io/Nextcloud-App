<?php

declare(strict_types=1);

namespace OCA\Olvid\Profile;

use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Db\OlvidUserMapper;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Profile\ILinkAction;

class OlvidProfileLinkAction implements ILinkAction {
	private bool $hasIdentity = false;

	public function __construct(
		private readonly OlvidUserMapper $olvidUserMapper,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l10n,
	) {
	}

	public function preload(IUser $targetUser): void {
		$olvidUser = $this->olvidUserMapper->getByUserIdOrNull($targetUser->getUID());
		$this->hasIdentity = $olvidUser?->hasIdentity() ?? false;
	}

	public function getAppId(): string {
		return Application::APP_ID;
	}

	public function getId(): string {
		return Application::APP_ID;
	}

	public function getDisplayId(): string {
		return $this->l10n->t('Olvid');
	}

	public function getTitle(): string {
		return $this->l10n->t('Use Olvid');
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
		return $this->hasIdentity ? $this->urlGenerator->linkToRoute('olvid.Page.index') : null;
	}
}
