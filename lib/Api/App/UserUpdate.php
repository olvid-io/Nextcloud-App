<?php

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use OCP\IUser;

class UserUpdate {
	public function __construct(
		IRequest $request,
		private readonly OlvidContext $context,
	) {
	}

	/**
	 * Update Olvid Details for a user. If necessary sign new details and notify users for the change.
	 *
	 * @throws Exception
	 */
	public function handle(IUser $nextcloudUser, String $newFirstname, String $newLastname, String $newPosition, String $newCompany): DataResponse {
		$olvidUser = $this->context->db->user->getOrCreate($nextcloudUser->getUID(), $nextcloudUser->getDisplayName());

		// update details
		$updated = false;
		if ($olvidUser->getFirstname() !== $newFirstname) {
			$olvidUser->setFirstname($newFirstname);
			$updated = true;
		}
		if ($olvidUser->getLastname() !== $newLastname) {
			$olvidUser->setLastname($newLastname);
			$updated = true;
		}
		if ($olvidUser->getPosition() !== $newPosition) {
			$olvidUser->setPosition($newPosition);
			$updated = true;
		}
		if ($olvidUser->getCompany() !== $newCompany) {
			$olvidUser->setCompany($newCompany);
			$updated = true;
		}

		// If none of firstname and lastname is set return an error
		if (!$olvidUser->getFirstname() && !$olvidUser->getLastname()) {
			return new DataResponse('At least one of firstname and lastname must be set', 400);
		}

		// details did not change, stop here
		if (!$updated) {
			return new DataResponse([]);
		}

		// re-compute details and sign them
		$userDetails = $olvidUser->computeJsonUserDetails();
		$olvidUser->setSignedDetails($this->context->signatory->sign($userDetails->jsonSerialize()));

		// update full search field
		$olvidUser->setFullSearchField($userDetails->computeFullSearchString());

		// update in database
		$olvidUser = $this->context->db->user->update($olvidUser);

		// notify user for change (if he registered)
		if ($olvidUser->hasIdentity()) {
			$this->context->olvidServer->sendSingleUserNotificationNoFail($userDetails->base64Identity);
		}

		return new DataResponse();
	}
}
