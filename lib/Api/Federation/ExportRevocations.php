<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Federation;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;

/**
 * POST /federations/revocations
 *
 * Server-to-server incremental export of this instance's revocation records, for a
 * trusted peer's own sync job to pull. `signedRevocation` is relayed verbatim — revocation
 * payloads are keyed by identity bytes only (see JsonRevocationData), never a uid, so
 * unlike users they need no federated-form rewriting.
 *
 * Request body: {callerUrl, sharedSecret, timestamp}
 * Response: {revocations: [{remoteUserId, bytesIdentity, revocationType, signedRevocation,
 *                            timestamp}], timestamp}
 */
class ExportRevocations extends AbstractFederationApiHandler {
	public function handler(array $jsonParameters): JSONResponse {
		$timestamp = (int)($jsonParameters[Constants::FEDERATION_REQUEST_TIMESTAMP] ?? 0);

		$revocations = [];
		foreach ($this->context->db->revocation->getSinceTimestampOrNull($timestamp) ?? [] as $revocation) {
			$revocations[] = [
				Constants::FEDERATION_REVOCATION_REMOTE_USER_ID => $revocation->getUserId(),
				Constants::FEDERATION_REVOCATION_BYTES_IDENTITY => base64_encode($revocation->getBytesIdentity()),
				Constants::FEDERATION_REVOCATION_TYPE => $revocation->getRevocationType(),
				Constants::FEDERATION_REVOCATION_SIGNED_REVOCATION => $revocation->getSignedRevocation(),
				Constants::FEDERATION_REVOCATION_TIMESTAMP => $revocation->getTimestamp(),
			];
		}

		return new JSONResponse([
			Constants::FEDERATION_REVOCATIONS_RESPONSE_REVOCATIONS => $revocations,
			Constants::FEDERATION_RESPONSE_TIMESTAMP => TimeUtil::currentTimeMillis(),
		]);
	}
}
