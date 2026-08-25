<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;

class GetKey extends AbstractAuthenticatedDeviceApiHandler {
	public function __construct(
		IRequest $request,
		LoggerInterface $logger,
		ILockingProvider $lockingProvider,
		OlvidContext $context,
	) {
		parent::__construct($request, $logger, $lockingProvider, $context);
	}

	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
		// Parse request
		try {
			$otherNextcloudUserId = isset($jsonParameters[Constants::GET_KEY_REQUEST_USER_ID]) ? (string)$jsonParameters[Constants::GET_KEY_REQUEST_USER_ID] : null;
			if (!$otherNextcloudUserId) {
				return $this->invalidRequest();
			}
		} catch (Exception $e) {
			$this->logger->warning('getKey: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		// a federated identity (`remoteUid@domain`) is served straight from the local cache;
		// see FederatedIdentityResolver for the domain-matching disambiguation rule
		$federatedMatch = $this->context->federation->resolveFederatedUserOrNull($otherNextcloudUserId);
		if ($federatedMatch !== null) {
			$response[Constants::GET_KEY_RESPONSE_SIGNATURE] = $federatedMatch->user->getSignedDetails();
			return new JSONResponse($response);
		}

		// get user in database
		$otherOlvidUser = $this->context->db->user->getByUserIdOrNull($otherNextcloudUserId);
		if (!$otherOlvidUser?->getSignedDetails()) {
			return $this->invalidRequest();
		}

		// set user signed details in response
		$response[Constants::GET_KEY_RESPONSE_SIGNATURE] = $otherOlvidUser->getSignedDetails();

		return new JSONResponse($response);
	}
}
