<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JsonField {
	public function __construct(
		public readonly string  $key,
		public readonly bool    $isBytes = false,
		public readonly ?string $class = null,
		public readonly bool    $isArray = false,
		public readonly bool    $excludeFromJson = false,
	) {}
}
