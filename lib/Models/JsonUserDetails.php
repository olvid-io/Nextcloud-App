<?php

namespace OCA\Olvid\Models;

use JsonSerializable;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Db\OlvidUser;
use OCA\Olvid\Utils\TimeUtil;

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

	public function computeFullSearchString(): String {
		return JsonUserDetails::unAccent($this->firstname == null ? '' : $this->firstname) . ' '
			. ($this->lastname == null ? '': $this->lastname) . ' '
			. ($this->position == null ? '': $this->position) . ' '
			. ($this->company == null ? '': $this->company);
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
