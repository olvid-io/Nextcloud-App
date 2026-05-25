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

	public static function computeDetails(IUser $user, IConfig $config) : OlvidUserDetails {
		// prepare details
		$id = $user->getUID();

		// get user details from attributes
		$firstname = $config->getUserValue($user->getUID(),
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_FIRSTNAME
		);
		$lastname = $config->getUserValue($user->getUID(),
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_LASTNAME
		);
		$position = $config->getUserValue($user->getUID(),
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_POSITION
		);
		$company = $config->getUserValue($user->getUID(),
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_COMPANY
		);

		// fallback: if user does not set any field we use display name as a first name
		if (!$firstname && !$lastname && !$position && !$company) {
			$firstname = $user->getDisplayName();
			// TODO keep ?
//			$config->setUserValue($user->getUID(),
//				Application::APP_ID,
//				Constants::USER_ATTRIBUTE_OLVID_FIRSTNAME,
//				$firstname);
		}

		$identity = trim($config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY));
		// set identity to null and not to an empty string, else android think we already have an identity on server ...
		if (!$identity) {
			$identity = null;
		}
		return new OlvidUserDetails($id, $firstname, $lastname, $position, $company, $identity, time());
	}

	// compute UserDetails signature, save it in database and return it
	public function sign(IConfig $config, IAppConfig $appConfig): string
	{
		// get signature key
		$keyId = AppConfigManager::getJwkKeyId($appConfig);
		$keyType = AppConfigManager::getJwkKeyType($appConfig);
		$privateKey = AppConfigManager::getJwkKeyPrivateKey($appConfig);

		// sign details and store in database
		$signedDetails = JWT::encode($this->jsonSerialize(), $privateKey, $keyType, $keyId);
		$config->setUserValue($this->id, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS, $signedDetails);
		return $signedDetails;
	}

	// try to parse signed details for a user, this allows to get details only for user that properly registered on server
	public static function parseSignedDetails(IUser $user, IConfig $config): ?OlvidUserDetails {
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
			// set identity to null and not to an empty string, else android think we already have an identity on server ...
			trim($details[Constants::DETAILS_KEY_IDENTITY]) ? $details[Constants::DETAILS_KEY_IDENTITY] : null,
			$details[Constants::DETAILS_KEY_TIMESTAMP] ?? 0,
		);
	}

	public function computeFullSearchString(): string {
        return OlvidUserDetails::unAccent($this->firstname == null ? "" : $this->firstname) . " " .
			($this->lastname == null ? "": $this->lastname) . " " .
			($this->position == null ? "": $this->position) . " " .
			($this->company == null ? "": $this->company);
    }

	# taken from https://gist.github.com/lohic/d01c458e69be636c2365
	private static function unAccent(string $str): string {
		// transform accent to html entities
		$str = htmlentities($str, ENT_NOQUOTES, 'utf-8');
		// replace html entities with non accentuated characters
		$str = preg_replace('#&([A-za-z])(?:acute|grave|cedil|circ|orn|ring|slash|th|tilde|uml);#', '\1', $str);
		// replace ligature: Œ, Æ ...
		$str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
		// delete the rest
		// $str = preg_replace('#&[^;]+;#', '', $str);
		return $str;
	}

	public function jsonSerialize(): array {
		return [
			Constants::DETAILS_KEY_ID => $this->id,
			// set identity to null and not to an empty string
			Constants::DETAILS_KEY_IDENTITY => $this->identity && trim($this->identity) ? $this->identity : null,
			Constants::DETAILS_KEY_FIRST_NAME => $this->firstname,
			Constants::DETAILS_KEY_LAST_NAME => $this->lastname,
			Constants::DETAILS_KEY_POSITION => $this->position,
			Constants::DETAILS_KEY_COMPANY => $this->company,
			Constants::DETAILS_KEY_TIMESTAMP => $this->timestamp,
		];
	}
}
