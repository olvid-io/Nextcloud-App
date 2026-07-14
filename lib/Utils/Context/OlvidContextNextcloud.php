<?php

namespace OCA\Olvid\Utils\Context;

use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\IGroupManager;
use OCP\IUserManager;

class OlvidContextNextcloud {
	public function __construct(
		public readonly OlvidAppConfigManager $appManager,
		public readonly IGroupManager $groupManager,
		public readonly IUserManager $userManager,
	) {
	}

}
