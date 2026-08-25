<?php

namespace OCA\Olvid\Utils\Context;

use OCA\Olvid\Db\OlvidDataMapper;
use OCA\Olvid\Db\OlvidFederatedRevocationMapper;
use OCA\Olvid\Db\OlvidFederatedServerMapper;
use OCA\Olvid\Db\OlvidFederatedUserMapper;
use OCA\Olvid\Db\OlvidGroupDeletionMapper;
use OCA\Olvid\Db\OlvidGroupKickedMapper;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCA\Olvid\Db\OlvidRevocationMapper;
use OCA\Olvid\Db\OlvidUserMapper;

class OlvidContextDatabase {
	public function __construct(
		public readonly OlvidUserMapper $user,
		public readonly OlvidGroupMapper $group,
		public readonly OlvidRevocationMapper $revocation,
		public readonly OlvidGroupKickedMapper $groupKicked,
		public readonly OlvidGroupDeletionMapper $groupDeletion,
		public readonly OlvidDataMapper $data,
		public readonly OlvidFederatedServerMapper $federatedServer,
		public readonly OlvidFederatedUserMapper $federatedUser,
		public readonly OlvidFederatedRevocationMapper $federatedRevocation,
	) {
	}

}
