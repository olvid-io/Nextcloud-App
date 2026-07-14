<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

/**
 * POST /olvid-rest/groups
 */
class Groups extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @throws \OCP\DB\Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): Response {
		// parse request (don't fail on parse error)
		try {
			$requestTimestamp = array_key_exists(Constants::GROUPS_REQUEST_TIMESTAMP, $jsonParameters) ? $jsonParameters[Constants::GROUPS_REQUEST_TIMESTAMP] : null;
		} catch (Exception $e) {
			$this->logger->warning('groups: parse error: ', ['exception' => $e]);
		}

		$currentTimestamp = TimeUtil::currentTimeMillis();

		// signed blobs
		// first get all user groups and return those updated recently enough
		$signedGroupBlobs = [];
		$userNextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($nextcloudUser);
		foreach ($userNextcloudGroups as $nextcloudGroup) {
			// get associated olvid group
			$olvidGroup = $this->context->db->group->getByGroupIdOrNull($nextcloudGroup->getGID());

			// only add enabled groups
			if (!$olvidGroup?->getEnabled()) {
				continue ;
			}

			// only add groups that were modified recently
			if ($requestTimestamp === null || $requestTimestamp < $olvidGroup->getLastModificationTimestamp()) {
				$signedGroupBlobs[] = $olvidGroup->getSignedGroupBlob();
			}
		}

		$earliestRevocationTimestamp = $requestTimestamp != null ? $requestTimestamp : ($currentTimestamp - Constants::DEFAULT_REVOCATION_LISTS_MAX_AGE_MILLIS);

		// get all deleted groups
		$signedGroupDeletions = $this->context->db->groupDeletion->getAfterTimestamp($earliestRevocationTimestamp);

		// get all groups user was removed from
		$signedGroupKicks = $this->context->db->groupKicked->getByUserIdAfterTimestamp($nextcloudUser->getUID(), $earliestRevocationTimestamp);

		$response = [
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_BLOBS => count($signedGroupBlobs) ? $signedGroupBlobs : [],
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_DELETIONS => count($signedGroupDeletions) ? array_map(fn ($v) => $v->getSignedDeletion(), $signedGroupDeletions) : [],
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_KICKS => count($signedGroupKicks) ? array_map(fn ($v) => $v->getSignedKick(), $signedGroupKicks) : [],
			Constants::GROUPS_RESPONSE_CURRENT_TIMESTAMP => $currentTimestamp,
		];

		return new JSONResponse($response);
	}
}
