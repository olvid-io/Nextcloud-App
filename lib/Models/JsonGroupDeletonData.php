<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupDeletonData implements JsonSerializable {
	public ?string $groupUid;
	public int $timestamp;

	public function __construct(?string $groupUid = null, int $timestamp = 0) {
		$this->groupUid  = $groupUid;
		$this->timestamp = $timestamp;
	}

	public static function fromArray(array $data): self {
		return new self(
			isset($data['groupUid']) ? base64_decode($data['groupUid']) : null,
			$data['timestamp'] ?? 0,
		);
	}

	public function jsonSerialize(): array {
		return array_filter([
			'groupUid'  => $this->groupUid !== null ? base64_encode($this->groupUid) : null,
			'timestamp' => $this->timestamp,
		], fn($v) => $v !== null);
	}
}
