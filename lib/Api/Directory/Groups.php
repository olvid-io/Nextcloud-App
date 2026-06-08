<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

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
			$this->logger->warning('groups: parse error: ', ['exception' => $e]);
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
			if ($requestTimestamp === null || $requestTimestamp < $olvidGroup->getLastModificationTimestamp()) {
				$signedGroupBlobs[] = $olvidGroup->getSignedGroupBlob();
			}
		}

		$earliestRevocationTimestamp = $requestTimestamp != null ? $requestTimestamp : ($currentTimestamp - Constants::DEFAULT_REVOCATION_LISTS_MAX_AGE_MILLIS);

		// get all deleted groups
		$signedGroupDeletions = $this->db->groupDeletion->getSignatureAfterTimestamp($earliestRevocationTimestamp);

		// get all groups user was removed from
		$signedGroupKicks = $this->db->groupKicked->getSignatureAfterTimestamp($user->getUID(), $earliestRevocationTimestamp);

		$response = [
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_BLOBS => count($signedGroupBlobs) ? $signedGroupBlobs : [],
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_DELETIONS => count($signedGroupDeletions) ? array_map(fn ($v) => $v->getSignature(), $signedGroupDeletions) : [],
			Constants::GROUPS_RESPONSE_SIGNED_GROUP_KICKS => count($signedGroupKicks) ? array_map(fn ($v) => $v->getSignature(), $signedGroupKicks) : [],
			Constants::GROUPS_RESPONSE_CURRENT_TIMESTAMP => $currentTimestamp,
		];

		return new JSONResponse($response);
	}
}
