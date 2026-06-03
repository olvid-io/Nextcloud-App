<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupMemberKickedData implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField('groupUid', isBytes: true)]
	public ?string $groupUid = null;

	#[JsonField('identity')]
	public ?string $identity = null;

	#[JsonField('timestamp')]
	public int $timestamp = 0;
}
