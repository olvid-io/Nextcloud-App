<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupDetails implements JsonSerializable {
	public ?string $name;
	public ?string $description;

	public function __construct(?string $name = null, ?string $description = null) {
		$this->name        = self::nullOrTrim($name);
		$this->description = self::nullOrTrim($description);
	}

	public static function fromArray(array $data): self {
		return new self($data['name'] ?? null, $data['description'] ?? null);
	}

	public function isEmpty(): bool {
		return $this->name === null;
	}

	public function equals(self $other): bool {
		return $this->name === $other->name
			&& $this->description === $other->description;
	}

	public function jsonSerialize(): array {
		return array_filter([
			'name'        => $this->name,
			'description' => $this->description,
		], fn($v) => $v !== null);
	}

	private static function nullOrTrim(?string $value): ?string {
		if ($value === null) {
			return null;
		}
		$trimmed = trim($value);
		return $trimmed !== '' ? $trimmed : null;
	}
}
