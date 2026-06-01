<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JsonField {
	public function __construct(
		public readonly string  $key,
		public readonly bool    $isBytes = false,
		// for object fields specify associated class
		public readonly ?string $class = null,
		public readonly bool    $isArray = false,
		// exclude this attribute from json fields
		public readonly bool    $excludeFromJson = false,
		// for object field, if all fields are empty serialize an empty object ({}) and not null.
		public readonly bool    $emptyAsObject = false,
	) {}
}
