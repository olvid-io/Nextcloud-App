<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Federation;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;

/**
 * POST /federations/users
 *
 * Server-to-server incremental export of this instance's enrolled Olvid users, for a
 * trusted peer's own sync job to pull. Each entry's `signedDetails` is a federated-form
 * JWT — same payload shape as a local user's signed details, except `id` is
 * `localUid@thisInstanceDomain` (see OlvidUser::computeFederatedJsonUserDetails()).
 *
 * Deliberately does NOT also send plaintext firstname/lastname/position/company/identity
 * fields alongside the signed blob: the receiving side derives everything it caches from
 * the verified JWT payload instead of trusting separately-sent claims from the peer.
 * `registrationTimestamp` is the one exception — it's the sync cursor, not security
 * sensitive, and isn't part of the signed payload (which carries a signing timestamp, not
 * the registration one).
 *
 * Request body: {callerUrl, sharedSecret, timestamp}
 * Response: {users: [{signedDetails, registrationTimestamp}], timestamp}
 */
class ExportUsers extends AbstractFederationApiHandler {
	public function handler(array $jsonParameters): JSONResponse {
		$timestamp = (int)($jsonParameters[Constants::FEDERATION_REQUEST_TIMESTAMP] ?? 0);
		$domain = $this->getOwnDomain();

		$users = [];
		foreach ($this->context->db->user->listRegisteredUsersSince($timestamp) as $olvidUser) {
			$federatedDetails = $olvidUser->computeFederatedJsonUserDetails($domain);
			$users[] = [
				Constants::FEDERATION_USER_SIGNED_DETAILS => $this->context->signatory->sign($federatedDetails->jsonSerialize()),
				Constants::FEDERATION_USER_REGISTRATION_TIMESTAMP => $olvidUser->getRegistrationTimestamp(),
			];
		}

		return new JSONResponse([
			Constants::FEDERATION_USERS_RESPONSE_USERS => $users,
			Constants::FEDERATION_RESPONSE_TIMESTAMP => TimeUtil::currentTimeMillis(),
		]);
	}
}
