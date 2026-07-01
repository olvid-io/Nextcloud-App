<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonRevocationData implements JsonSerializable {
	use JsonSerializableTrait;

	public const REVOCATION_TYPE_REVOKE_ID = 0;
	public const REVOCATION_TYPE_DELETE_USER = 1;
	public const REVOCATION_TYPE_DISABLE_USER = 2;


	#[JsonField('identity', isBytes: true)]
	public ?string $identity = null;

	#[JsonField('timestamp')]
	public int $timestamp = 0;

	#[JsonField('revocationType')]
	public int $revocationType = 0;
}
