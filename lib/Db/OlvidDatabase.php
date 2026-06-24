<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

class OlvidDatabase {
	public function __construct(
		public readonly OlvidGroupMapper $group,
		public readonly OlvidRevocationMapper $revocation,
		public readonly OlvidGroupKickedMapper $groupKicked,
		public readonly OlvidGroupDeletionMapper $groupDeletion,
		public readonly OlvidDataMapper $dataMapper,
	) {
	}
}
