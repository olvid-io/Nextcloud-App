<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Db\OlvidDataMapper;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\DB\Exception;

/**
 * GET /app/groups/{groupId}/avatar?photoUid=
 *
 * Returns the raw JPEG bytes stored in the olvid_data table for this group's
 * group_photo_uid.
 * We check that the photoUid is valid to check user is allowed to see the group.
 * The response carries an immutable Cache-Control header
 * because the URL is versioned with a ?photoUid=<photoUid> query parameter; when the
 * photo changes a new UID is assigned and the browser fetches the new URL.
 */
class GroupAvatarGet {
	public function __construct(
		private readonly OlvidGroupMapper $olvidGroupMapper,
		private readonly OlvidDataMapper $olvidDataMapper,
	) {
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function handle(string $groupId, string $b64PhotoUid): DataDisplayResponse {
		// Load the OlvidGroup
		$olvidGroup = $this->olvidGroupMapper->findByGroupIdOrNull($groupId);
		if ($olvidGroup === null || $olvidGroup->getGroupPhotoUid() === null) {
			return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
		}

		// get photo by UID (this checks user got access to the current avatar ID)
		$olvidData = $this->olvidDataMapper->getByUidOrNull($b64PhotoUid);
		if ($olvidData === null) {
			return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
		}

		return new DataDisplayResponse($olvidData->getData(), Http::STATUS_OK);
	}
}
