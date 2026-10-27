<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils\Context;

class OlvidContextNextcloud {
	public function __construct(
		public readonly OlvidContextAppManager $appManager,
		public readonly OlvidContextGroupManager $groupManager,
		public readonly OlvidContextUserManager $userManager,
	) {
	}

}
