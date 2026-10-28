<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupDetails implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField('name')]
	public ?string $name = null;

	#[JsonField('description')]
	public ?string $description = null;

	public function __construct(?string $name = null, ?string $description = null) {
		$this->name = $this->nullOrTrim($name);
		$this->description = $this->nullOrTrim($description);
	}

	public function isEmpty(): bool {
		return $this->name === null;
	}

	public function equals(self $other): bool {
		return $this->name === $other->name
			&& $this->description === $other->description;
	}

	private function nullOrTrim(?string $value): ?string {
		if ($value === null) {
			return null;
		}

		$trimmed = trim($value);
		return $trimmed !== '' ? $trimmed : null;
	}
}
