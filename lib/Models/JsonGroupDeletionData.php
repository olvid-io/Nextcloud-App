<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupDeletionData implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField('groupUid', isBytes: true)]
	public ?string $bytesGroupUid = null;

	#[JsonField('timestamp')]
	public int $timestamp = 0;
}
