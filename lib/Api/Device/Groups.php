<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

/**
 * POST /olvid-rest/groups
 */
class Groups extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $user): Response {
		// parse request (don't fail on parse error)
		try {
			$requestTimestamp = array_key_exists(Constants::GROUPS_REQUEST_TIMESTAMP, $jsonParameters) ? $jsonParameters[Constants::GROUPS_REQUEST_TIMESTAMP] : null;
		} catch (Exception $e) {
			$this->logger->warning('groups: parse error: ' . $e->getMessage());
		}

		$currentTimestamp = TimeUtil::currentTimeMillis();

		// signed blobs
		// first get all user groups and return those updated recently enough
		$signedGroupBlobs = [];
		$userGroups = $this->groupManager->getUserGroups($user);
		foreach ($userGroups as $group) {
			// get associated olvid group
			$olvidGroup = $this->db->group->findByGroupIdOrNull($group->getGID());

			// only add enabled groups
			if (!$olvidGroup?->getEnabled()) {
				continue ;
			}

			// only add groups that were modified recently
			$lastModificationTimestamp = $olvidGroup->getLastModificationTimestamp();
			if ($requestTimestamp === null ||  $requestTimestamp < $lastModificationTimestamp) {
				$signedGroupBlobs[] = $olvidGroup->getSignedGroupBlob();
			}
		}

		$earliestRevocationTimestamp = $requestTimestamp != null ? $requestTimestamp : ($currentTimestamp - Constants::DEFAULT_REVOCATION_LISTS_MAX_AGE_MILLIS);

		// get all deleted groups
		$signedGroupDeletions = [];

		// get all groups user was removed from
		$signedGroupKicks = [];

		return new JSONResponse([
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_BLOBS => count($signedGroupBlobs) ? $signedGroupBlobs : null,
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_DELETIONS => count($signedGroupDeletions) ? $signedGroupDeletions : null,
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_KICKS => count($signedGroupKicks) ? $signedGroupKicks : null,
			Constants::GROUPS_RESPONSE_CURRENT_TIMESTAMP => $currentTimestamp,
		]);
	}
}
