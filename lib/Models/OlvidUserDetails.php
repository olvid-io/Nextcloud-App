<?php

namespace OCA\Olvid\Models;

use Firebase\JWT\JWT;
use JsonSerializable;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUser;
use OCP\PreConditionNotMetException;

class OlvidUserDetails implements JsonSerializable {
	public String $id;
    public ?String $identity;
    public String $firstname;
    public String $lastname;
    public ?String $position;
    public ?String $company;
    public int $timestamp;

	/**
	 * @param int $timestamp
	 * @param String $company
	 * @param String $position
	 * @param String $lastname
	 * @param String $firstname
	 * @param ?String $identity
	 * @param String $id
	 */
	private function __construct(string $id, string $firstname, string $lastname, string $position, string $company, ?string $identity, int $timestamp) {
		$this->timestamp = $timestamp;
		$this->company = $company;
		$this->position = $position;
		$this->lastname = $lastname;
		$this->firstname = $firstname;
		$this->identity = $identity;
		$this->id = $id;
	}

	public static function getCurrentUserDetails(IUser $user, IConfig $config): ?OlvidUserDetails {
		$signedDetails = $config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS);
		if (!$signedDetails) {
			return null;
		}

		$encodedDetails = explode(".", $signedDetails)[1];
		$jsonDetails = base64_decode($encodedDetails);
		$details = json_decode($jsonDetails, true);

		return new OlvidUserDetails(
			$details[Constants::DETAILS_KEY_ID] ?? "",
			$details[Constants::DETAILS_KEY_FIRST_NAME] ?? "",
			$details[Constants::DETAILS_KEY_LAST_NAME] ?? "",
			$details[Constants::DETAILS_KEY_POSITION] ?? "",
			$details[Constants::DETAILS_KEY_COMPANY] ?? "",
			trim($details[Constants::DETAILS_KEY_IDENTITY]) ? $details[Constants::DETAILS_KEY_IDENTITY] : null,
			$details[Constants::DETAILS_KEY_TIMESTAMP] ?? 0,
		);
	}

	/*
	 * sign user details and store them in user attributes
	 */
	/**
	 * @throws PreConditionNotMetException
	 */
	public static function signUserDetails (IUser $user, IConfig $config, IAppConfig $appConfig): string {
		// prepare details
		$id = $user->getUID();
		$firstname = $user->getDisplayName();
		$identity = trim($config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY));
		// set identity to null and not to an empty string, else android think we already have an identity on server ...
		if (!$identity) {
			$identity = null;
		}
		$olvidUserDetails = new OlvidUserDetails($id, $firstname, "", "", "", $identity, time());

		// get signature key
		$keyId = AppConfigManager::getJwkKeyId($appConfig);
		$keyType = AppConfigManager::getJwkKeyType($appConfig);
		$privateKey = AppConfigManager::getJwkKeyPrivateKey($appConfig);

		$signedDetails = JWT::encode($olvidUserDetails->jsonSerialize(), $privateKey, $keyType, $keyId);
        $config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS, $signedDetails);
		return $signedDetails;
	}

	public function getFullSearchString(): string {
		// TODO remove accents in string
        return ($this->firstname == null ? "" : $this->firstname) . " " . ($this->lastname == null ? "": $this->lastname) . " " . ($this->position == null ? "": $this->position) . " " . ($this->company == null ? "": $this->company);
    }

	public function jsonSerialize(): array {
		return [
			Constants::DETAILS_KEY_ID => $this->id,
			Constants::DETAILS_KEY_IDENTITY => trim($this->identity) ? $this->identity : null, // set identity to null and not to an empty string
			Constants::DETAILS_KEY_FIRST_NAME => $this->firstname,
			Constants::DETAILS_KEY_LAST_NAME => $this->lastname,
			Constants::DETAILS_KEY_POSITION => $this->position,
			Constants::DETAILS_KEY_COMPANY => $this->company,
			Constants::DETAILS_KEY_TIMESTAMP => $this->timestamp,
		];
	}
}
