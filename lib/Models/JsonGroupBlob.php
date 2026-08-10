<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupBlob implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField('', excludeFromJson: true)]
	public string $groupId = '';

	#[JsonField('guid', isBytes: true)]
	public ?string $bytesGroupUid = null;

	#[JsonField('details', class: JsonGroupDetails::class)]
	public ?JsonGroupDetails $groupDetails = null;

	#[JsonField('photo_label', isBytes: true)]
	public ?string $photoUid = null;

	#[JsonField('photo_key', isBytes: true)]
	public ?string $encodedPhotoKey = null;

	#[JsonField('pt')]
	public ?string $pushTopic = null;

	#[JsonField('gm_perms', class: JsonGroupMemberAndPermissions::class, isArray: true)]
	public array $groupMembersAndPermissions = [];

	#[JsonField('sss')]
	public ?string $serializedSharedSettings = null;

	#[JsonField('timestamp')]
	public int $timestamp = 0;

	public static function fromArray(array $data, string $groupId): self {
		$instance = static::hydrateFromArray($data);
		$instance->groupId = $groupId;
		return $instance;
	}
}
