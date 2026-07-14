<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Http\BinaryResponse;

/**
 * POST /olvid-rest/revocationTest
 *
 * Mixed protocol: JSON request body, binary Encoded response.
 *
 * To validate he has not been revoked when logged out, Olvid engine can call this entrypoint.
 * It passes a nonce he received when he registered on server (and stored as a user attribute).
 * If the nonce is still associated with the user, the user was not revoked, you can log again.
 * Else, the nonce was removed during revocation / user deletion process, so the app can conclude
 * the identity was revoked or deleted from server.
 *
 * Returns \x00 if the nonce was found, \x01 else or if an error happened
 */
class RevocationTest extends AbstractEngineApiHandler {
	/**
	 * @throws \OCP\DB\Exception
	 */
	public function handler(string $rawInput): BinaryResponse {
		// parse request
		try {
			$json = json_decode($rawInput, true);
			$nonce = $json[Constants::REVOCATION_TEST_REQUEST_NONCE] ?? null;
			if ($nonce === null) {
				throw new Exception('Missing nonce field');
			}
		} catch (Exception $e) {
			$this->logger->warning('revocationTest: parse error: ', ['exception' => $e]);
			return $this->generalError();
		}

		// check nonce is not empty (else all it will match everyone)
		if (trim($nonce) === '') {
			return new BinaryResponse("\x01");
		}

		// get user associated with this nonce
		$olvidUsersForNonce = $this->context->db->user->searchNonce($nonce);
		if (count($olvidUsersForNonce) === 0) {
			$this->logger->error('revocationTest: nonce not found');
			return new BinaryResponse("\x01");
		}
		if (count($olvidUsersForNonce) > 1) {
			$this->logger->error('revocationTest: found more than one user with the same nonce');
			return new BinaryResponse("\x01");
		}

		// if we found a user associated with this nonce this means he was not revoked
		return new BinaryResponse("\x00");
	}
}
