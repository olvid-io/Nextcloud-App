<?php

namespace OCA\Olvid\Models;

use Firebase\JWT\JWT;
use JsonSerializable;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\IUser;

class JsonUserDetails implements JsonSerializable {
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

	public static function computeDetails(IUser $user, OlvidUserConfigManager $userConfig) : OlvidUserDetails {
		// prepare details
		$id = $user->getUID();

		// get user details from attributes
		$firstname = $olvidUserConfig->getFirstname($user->getUID()) ?? '';
		$lastname  = $olvidUserConfig->getLastname($user->getUID()) ?? '';
		$position  = $olvidUserConfig->getPosition($user->getUID()) ?? '';
		$company   = $olvidUserConfig->getCompany($user->getUID()) ?? '';

		// fallback: if user does not set any field we use display name as a first name
		if (!$firstname && !$lastname && !$position && !$company) {
			$firstname = $user->getDisplayName();
			// TODO keep ?
//			$userConfig->setFirstname($user->getUID(), $firstname);
		}

		$identity = $olvidUserConfig->getIdentity($user->getUID());
		// set identity to null and not to an empty string, else android think we already have an identity on server ...
		if (!$identity) {
			$identity = null;
		}
		return new JsonUserDetails($id, $firstname, $lastname, $position, $company, $identity, TimeUtil::currentTimeMillis());
	}

	// compute UserDetails signature, save it in database and return it
	public function sign(OlvidUserConfigManager $olvidUserConfig, OlvidAppConfigManager $olvidAppConfig): string
	{
		// get signature key
		$keyId = $olvidAppConfig->getJwkKeyId();
		$keyType = $olvidAppConfig->getJwkKeyType();
		$privateKey = $olvidAppConfig->getJwkKeyPrivateKey();

		// sign details and store in database
		$signedDetails = JWT::encode($this->jsonSerialize(), $privateKey, $keyType, $keyId);
		$olvidUserConfig->setSignedDetails($this->id, $signedDetails);
		return $signedDetails;
	}

	// try to parse signed details for a user, this allows to get details only for user that properly registered on server
	public static function parseSignedDetails(IUser $user, OlvidUserConfigManager $userConfig): ?JsonUserDetails {
		$signedDetails = $userConfig->getSignedDetails($user->getUID());
		if (!$signedDetails) {
			return null;
		}

		$encodedDetails = explode(".", $signedDetails)[1];
		$jsonDetails = base64_decode($encodedDetails);
		$details = json_decode($jsonDetails, true);

		return new JsonUserDetails(
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
        return JsonUserDetails::unAccent($this->firstname == null ? "" : $this->firstname) . " " .
			($this->lastname == null ? "": $this->lastname) . " " .
			($this->position == null ? "": $this->position) . " " .
			($this->company == null ? "": $this->company);
    }

 	public function updateFullSearchString(string $userId, OlvidUserConfigManager $userConfig): string {
		// set or update full search string attributes
		$fullSearchString = $this->computeFullSearchString();
		if ($fullSearchString !== $userConfig->getFullSearchField($userId)) {
			$userConfig->setFullSearchField($userId, $fullSearchString);
		}
		return $fullSearchString;
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
