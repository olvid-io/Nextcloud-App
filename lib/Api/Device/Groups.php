<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use OCA\Olvid\Api\Constants;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

/**
 * POST /olvid-rest/groups
 */
class Groups extends AbstractDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $user): Response {
		// parse request (don't fail on parse error)
		try {
			$timestamp = (int)($jsonParameters[Constants::GROUPS_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('groups: parse error: ' . $e->getMessage());
			return $this->invalidRequest();
		}

		// TODO: Implement handler() method.

		return new JSONResponse([
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_BLOBS => [],
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_DELETIONS => [],
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_KICKS => [],
			Constants::GROUPS_RESPONSE_CURRENT_TIMESTAMP => 0,
		]);
	}
}
