<?php

declare(strict_types=1);

// Minimal stubs for OCA\OIDCIdentityProvider classes.
// The real implementations live in the `oidc` Nextcloud app and are not
// available as a Composer dependency. These stubs let unit tests run
// standalone without a full Nextcloud environment.
// The class_exists guards prevent redefinition when running inside NC.

namespace OCA\OIDCIdentityProvider\Db {
	if (!class_exists(\OCA\OIDCIdentityProvider\Db\ClientMapper::class)) {
		class ClientMapper {
			public function getByIdentifier(string $identifier): ?Client {
				return null;
			}
			public function insert(object $client): object {
				return $client;
			}
		}
	}

	if (!class_exists(\OCA\OIDCIdentityProvider\Db\Client::class)) {
		class Client {
			public function __construct(
				string $name = '',
				array $redirectUris = [],
				string $algorithm = '',
				string $type = '',
				string $flowType = '',
				string $tokenType = '',
				string $allowedScopes = '',
				string $emailRegexp = '',
			) {
			}
			public function getClientIdentifier(): string {
				return 'test-client-id';
			}
			public function getSecret(): string {
				return 'test-client-secret';
			}
		}
	}
}

namespace OCA\OIDCIdentityProvider\Util {
	if (!class_exists(\OCA\OIDCIdentityProvider\Util\DiscoveryGenerator::class)) {
		class DiscoveryGenerator {
			public function generateDiscovery(\OCP\IRequest $request): \OCP\AppFramework\Http\JSONResponse {
				return new \OCP\AppFramework\Http\JSONResponse([]);
			}
		}
	}
}

namespace OCA\OIDCIdentityProvider\Event {
	if (!class_exists(\OCA\OIDCIdentityProvider\Event\TokenValidationRequestEvent::class)) {
		class TokenValidationRequestEvent {
			private bool $valid = false;
			private string $userId = '';
			public function __construct(string $token) {
			}
			public function getIsValid(): bool {
				return $this->valid;
			}
			public function setIsValid(bool $valid): void {
				$this->valid = $valid;
			}
			public function getUserId(): string {
				return $this->userId;
			}
			public function setUserId(string $userId): void {
				$this->userId = $userId;
			}
		}
	}
}

namespace OCA\OIDCIdentityProvider\Exceptions {
	if (!class_exists(\OCA\OIDCIdentityProvider\Exceptions\ClientNotFoundException::class)) {
		class ClientNotFoundException extends \Exception {
		}
	}
}
