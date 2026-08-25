<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils\Context\OlvidFederation;

use OCA\Olvid\Db\OlvidFederatedServer;
use OCA\Olvid\Db\OlvidFederatedUser;

class FederatedIdentityMatch {
	public function __construct(
		public readonly OlvidFederatedServer $server,
		public readonly OlvidFederatedUser $user,
	) {
	}
}
