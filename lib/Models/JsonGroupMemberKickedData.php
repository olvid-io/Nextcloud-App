<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupMemberKickedData implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField('groupUid', isBytes: true)]
	public ?string $bytesGroupUid = null;

	#[JsonField('identity')]
	public ?string $base64Identity = null;

	#[JsonField('timestamp')]
	public int $timestamp = 0;
}
