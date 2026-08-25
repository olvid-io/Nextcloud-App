<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils\Context;

class OlvidContext {
	public function __construct(
		public readonly OlvidContextDatabase $db,
		public readonly OlvidContextFederation $federation,
		public readonly OlvidContextNextcloud $nextcloud,
		public readonly OlvidContextServer $olvidServer,
		public readonly OlvidContextSignatory $signatory,
	) {
	}
}
