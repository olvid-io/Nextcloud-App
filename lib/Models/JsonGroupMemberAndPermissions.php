<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use JsonSerializable;

class JsonGroupMemberAndPermissions implements JsonSerializable {
	public ?string $keycloakUserId;
	public ?string $identityString;
	public ?string $signedUserDetails;
	/** @var string[] */
	public array $permissions;
	public ?string $groupInvitationNonce;

	public function __construct(
		?string $keycloakUserId = null,
		?string $identityString = null,
		?string $signedUserDetails = null,
		array $permissions = [],
		?string $groupInvitationNonce = null,
	) {
		$this->keycloakUserId      = $keycloakUserId;
		$this->identityString      = $identityString;
		$this->signedUserDetails   = $signedUserDetails;
		$this->permissions         = $permissions;
		$this->groupInvitationNonce = $groupInvitationNonce;
	}

	public static function fromArray(array $data): self {
		return new self(
			$data['id']          ?? null,
			$data['identity']    ?? null,
			$data['signature']   ?? null,
			$data['permissions'] ?? [],
			isset($data['nonce']) ? base64_decode($data['nonce']) : null,
		);
	}

	public function equals(self $other): bool {
		return $this->keycloakUserId === $other->keycloakUserId;
	}

	public function jsonSerialize(): array {
		return array_filter([
			'id'          => $this->keycloakUserId,
			'identity'    => $this->identityString,
			'signature'   => $this->signedUserDetails,
			'permissions' => $this->permissions,
			'nonce'       => $this->groupInvitationNonce !== null ? base64_encode($this->groupInvitationNonce) : null,
		], fn($v) => $v !== null);
	}
}
