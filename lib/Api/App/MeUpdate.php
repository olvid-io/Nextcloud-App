<?php

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class MeUpdate {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidServer $olvidServer,
	) {
	}

	public function handle(IUser $user): JSONResponse {
		$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];

		$previousUserDetails = JsonUserDetails::computeDetails($user, $this->olvidUserConfig);

		// update details
		$updated = false;
		if ($previousUserDetails->firstname !== $jsonParameters['firstname']) {
			$this->olvidUserConfig->setFirstname($user->getUID(), $jsonParameters['firstname']);
			$updated = true;
		}
		if ($previousUserDetails->lastname !== $jsonParameters['lastname']) {
			$this->olvidUserConfig->setLastname($user->getUID(), $jsonParameters['lastname']);
			$updated = true;
		}
		if ($previousUserDetails->position !== $jsonParameters['position']) {
			$this->olvidUserConfig->setPosition($user->getUID(), $jsonParameters['position']);
			$updated = true;
		}
		if ($previousUserDetails->company !== $jsonParameters['company']) {
			$this->olvidUserConfig->setCompany($user->getUID(), $jsonParameters['company']);
			$updated = true;
		}

		// details did not changed, stop here
		if (!$updated) {
			return new JSONResponse([]);
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

		return new JSONResponse();
	}
}
