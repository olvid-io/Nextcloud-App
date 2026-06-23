<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupMemberAndPermissions implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField('id')]
	public ?string $keycloakUserId = null;

	#[JsonField('identity')]
	public ?string $identityString = null;

	#[JsonField('signature')]
	public ?string $signedUserDetails = null;

	#[JsonField('permissions')]
	public array $permissions = [];

	#[JsonField('nonce', isBytes: true)]
	public ?string $groupInvitationNonce = null;

	public function __construct(
		?string $keycloakUserId = null,
		?string $identityString = null,
		?string $signedUserDetails = null,
		array $permissions = [],
		?string $groupInvitationNonce = null,
	) {
		$this->keycloakUserId = $keycloakUserId;
		$this->identityString = $identityString;
		$this->signedUserDetails = $signedUserDetails;
		$this->permissions = $permissions;
		$this->groupInvitationNonce = $groupInvitationNonce;
	}

	public function equals(self $other): bool {
		return $this->keycloakUserId === $other->keycloakUserId;
	}
}
