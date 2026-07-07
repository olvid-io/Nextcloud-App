<?php

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class UserUpdate {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidServer $olvidServer,
	) {
	}

	public function handle(IUser $user, String $newFirstname, String $newLastname, String $newPosition, String $newCompany): DataResponse {
		$previousUserDetails = JsonUserDetails::computeDetails($user, $this->olvidUserConfig);

		// update details
		$updated = false;
		if ($previousUserDetails->firstname !== $newFirstname) {
			$this->olvidUserConfig->setFirstname($user->getUID(), $newFirstname);
			$updated = true;
		}
		if ($previousUserDetails->lastname !== $newLastname) {
			$this->olvidUserConfig->setLastname($user->getUID(), $newLastname);
			$updated = true;
		}
		if ($previousUserDetails->position !== $newPosition) {
			$this->olvidUserConfig->setPosition($user->getUID(), $newPosition);
			$updated = true;
		}
		if ($previousUserDetails->company !== $newCompany) {
			$this->olvidUserConfig->setCompany($user->getUID(), $newCompany);
			$updated = true;
		}

		// details did not change, stop here
		if (!$updated) {
			return new DataResponse([]);
		}

		// re-compute details and sign them
		$userDetails = JsonUserDetails::computeDetails($user, $this->olvidUserConfig);
		$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);

		// update full search field
		$userDetails->updateFullSearchString($user->getUID(), $this->olvidUserConfig);

		// notify user for change (if he registered)
		if ($userDetails->identity) {
			try {
				$this->olvidServer->sendSingleUserNotification($userDetails->identity);
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('AppApiController: updateMe: cannot send user notification: ' . $exception->getMessage());
			}
		}

		return new DataResponse();
	}
}
