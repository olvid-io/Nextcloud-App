<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use Firebase\JWT\JWT;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidFederation\FederatedKeyRegistry;
use OCA\Olvid\Utils\Encoded;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;

/**
 * POST /olvid-rest/verify
 *
 * Mixed protocol: JSON request body, binary Encoded response.
 *
 * Request body: {"signature": "<ES256 JWT>"}
 *
 * The JWT is signed either by this server's own key, or by a trusted federated server's
 * key (identifiable via the JWT's `id` claim: `remoteUid@domain`, see
 * OlvidUser::computeFederatedJsonUserDetails()). Both cases are verified against the same
 * aggregated key set FederatedKeyRegistry publishes at /.well-known/jwks — the JWT
 * header's `kid` selects the right key automatically, no manual domain routing needed for
 * the signature check itself. Domain routing only decides which cache (local `olvid_user`
 * vs the federated cache) to check the decoded identity against afterwards.
 *
 * Response: Encoded list [STATUS_OK=0x00, boolean] on success,
 *           Encoded list [STATUS_GENERAL_ERROR=0xff] on parse/crypto error.
 */
class Verify extends AbstractEngineApiHandler {
	public function __construct(
		ICacheFactory $cacheFactory,
		LoggerInterface $logger,
		OlvidContext $context,
		private readonly FederatedKeyRegistry $federatedKeyRegistry,
	) {
		parent::__construct($cacheFactory, $logger, $context);
	}

	protected function handler(string $rawInput): BinaryResponse {
		// Parse request
		try {
			$json = json_decode($rawInput, true);
			$signature = $json[Constants::VERIFY_REQUEST_SIGNATURE] ?? null;
			if ($signature === null) {
				throw new Exception('Missing signature field');
			}
		} catch (Exception $e) {
			$this->logger->warning('verify: parse error: ', ['exception' => $e]);
			return $this->generalError();
		}

		// Verify JWT (own key or a trusted federated server's key) and check identity
		try {
			$decodedPayload = JWT::decode($signature, $this->federatedKeyRegistry->getAggregatedKeySet());
			$jsonUserDetails = JsonUserDetails::fromArray((array)$decodedPayload);
			/** @noinspection PhpConditionAlreadyCheckedInspection */
			if ($jsonUserDetails?->id === null || $jsonUserDetails?->base64Identity === null) {
				throw new Exception('Missing id or identity in JWT payload');
			}
			$userId = $jsonUserDetails->id;
			$base64Identity = $jsonUserDetails->base64Identity;

			$federatedMatch = $this->context->federation->resolveFederatedUserOrNull($userId);
			if ($federatedMatch !== null) {
				$identityMatches = base64_encode($federatedMatch->user->getBytesIdentity()) === $base64Identity;
				if (!$identityMatches) {
					$this->logger->debug('verify: federated identity mismatch for ' . $userId);
				}
				return $this->binaryResult($identityMatches);
			}

			// Check identity matches stored value
			$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);
			if ($olvidUser === null || !$olvidUser->hasIdentity()
				|| base64_encode($olvidUser->getBytesIdentity()) !== $base64Identity) {
				$this->logger->debug('verify: identity mismatch for user ' . $userId);
				return $this->binaryResult(false);
			}

			return $this->binaryResult(true);
		} catch (Exception $e) {
			$this->logger->warning('verify: error: ', ['exception' => $e]);
			return $this->generalError();
		}
	}

	private function binaryResult(bool $value): BinaryResponse {
		return new BinaryResponse(Encoded::encodeList([
			Encoded::encodeBytes(self::STATUS_OK),
			Encoded::encodeBoolean($value),
		]));
	}
}
