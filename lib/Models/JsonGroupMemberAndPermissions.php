<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupMemberAndPermissions implements JsonSerializable {
	use JsonSerializableTrait;

	public function __construct(
		#[JsonField('id')]
		public ?string $keycloakUserId = null,
		#[JsonField('identity')]
		public ?string $identityString = null,
		#[JsonField('signature')]
		public ?string $signedUserDetails = null,
		#[JsonField('permissions')]
		public array $permissions = [],
		#[JsonField('nonce', isBytes: true)]
		public ?string $groupInvitationNonce = null,
	) {
	}

	public function equals(self $other): bool {
		return $this->keycloakUserId === $other->keycloakUserId;
	}
}
