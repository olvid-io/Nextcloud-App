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
	use JsonSerializableTrait;

	#[JsonField(Constants::DETAILS_KEY_ID)]
	public String $id;
	#[JsonField(Constants::DETAILS_KEY_IDENTITY)]
	public ?String $identity;
	#[JsonField(Constants::DETAILS_KEY_FIRST_NAME)]
	public String $firstname;
	#[JsonField(Constants::DETAILS_KEY_LAST_NAME)]
	public String $lastname;
	#[JsonField(Constants::DETAILS_KEY_POSITION)]
	public ?String $position;
	#[JsonField(Constants::DETAILS_KEY_COMPANY)]
	public ?String $company;
	#[JsonField(Constants::DETAILS_KEY_TIMESTAMP)]
	public int $timestamp;

	// TODO what if details expired ? check java version
	// get signed details in db or compute them
	public static function getSignedDetails(IUser $user, OlvidUserConfigManager $olvidUserConfig, OlvidAppConfigManager $olvidAppConfig) : String {
		$signedDetails = $olvidUserConfig->getSignedDetails($user->getUID());
		if ($signedDetails) {
			return $signedDetails;
		} else {
			$details = JsonUserDetails::computeDetails($user, $olvidUserConfig);
			return $details->sign($olvidUserConfig, $olvidAppConfig);
		}
	}

	public static function computeDetails(IUser $user, OlvidUserConfigManager $olvidUserConfig) : JsonUserDetails {
		// prepare details
		$id = $user->getUID();

		// get user details from attributes
		$firstname = trim($olvidUserConfig->getFirstname($user->getUID()) ?? '');
		$lastname = trim($olvidUserConfig->getLastname($user->getUID()) ?? '');
		$position = trim($olvidUserConfig->getPosition($user->getUID()) ?? '');
		$company = trim($olvidUserConfig->getCompany($user->getUID()) ?? '');

		// fallback: if user does not set any of firstname and lastname we use display name as a first name
		if (!$firstname && !$lastname) {
			$firstname = $user->getDisplayName();
		}

		$identity = $olvidUserConfig->getB64Identity($user->getUID());
		// set identity to null and not to an empty string, else android think we already have an identity on server ...
		if (!$identity) {
			$identity = null;
		}
		$details = new JsonUserDetails();
		$details->id = $id;
		$details->firstname = $firstname;
		$details->lastname = $lastname;
		$details->position = $position;
		$details->company = $company;
		$details->identity = $identity;
		$details->timestamp = TimeUtil::currentTimeMillis();
		return $details;
	}

	// compute UserDetails signature, save it in database and return it
	public function sign(OlvidUserConfigManager $olvidUserConfig, OlvidAppConfigManager $olvidAppConfig): String {
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

		$encodedDetails = explode('.', $signedDetails)[1];
		$jsonDetailsString = base64_decode($encodedDetails);
		if (!$jsonDetailsString) {
			return null;
		}
		$jsonDetailsArray = json_decode($jsonDetailsString, true);
		if (!is_array($jsonDetailsArray)) {
			return null;
		}
		return JsonUserDetails::fromArray($jsonDetailsArray);
	}

	public function computeFullSearchString(): String {
		return JsonUserDetails::unAccent($this->firstname == null ? '' : $this->firstname) . ' '
			. ($this->lastname == null ? '': $this->lastname) . ' '
			. ($this->position == null ? '': $this->position) . ' '
			. ($this->company == null ? '': $this->company);
	}

	public function updateFullSearchString(String $userId, OlvidUserConfigManager $userConfig): String {
		// set or update full search string attributes
		$fullSearchString = $this->computeFullSearchString();
		if ($fullSearchString !== $userConfig->getFullSearchField($userId)) {
			$userConfig->setFullSearchField($userId, $fullSearchString);
		}
		return $fullSearchString;
	}

	# taken from https://gist.github.com/lohic/d01c458e69be636c2365
	private static function unAccent(String $str): String {
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
}
