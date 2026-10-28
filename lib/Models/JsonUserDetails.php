<?php

namespace OCA\Olvid\Models;

use JsonSerializable;
use OCA\Olvid\Api\Constants;

class JsonUserDetails implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField(Constants::DETAILS_KEY_ID)]
	public String $id;

	#[JsonField(Constants::DETAILS_KEY_IDENTITY)]
	public ?String $base64Identity = null;

	#[JsonField(Constants::DETAILS_KEY_FIRST_NAME)]
	public String $firstname;

	#[JsonField(Constants::DETAILS_KEY_LAST_NAME)]
	public String $lastname;

	#[JsonField(Constants::DETAILS_KEY_POSITION)]
	public ?String $position = null;

	#[JsonField(Constants::DETAILS_KEY_COMPANY)]
	public ?String $company = null;

	#[JsonField(Constants::DETAILS_KEY_TIMESTAMP)]
	public int $timestamp;

	public static function normalizeForSearch(string $str): string {
		return self::unAccent(mb_strtolower($str));
	}

	public function computeFullSearchString(): String {
		return self::normalizeForSearch(implode(' ', [$this->firstname ?? '', $this->lastname ?? '', $this->position ?? '', $this->company ?? '']));
	}

	# taken from https://gist.github.com/lohic/d01c458e69be636c2365
	private static function unAccent(String $str): String {
		// transform accent to html entities
		$str = htmlentities($str, ENT_NOQUOTES, 'utf-8');
		// replace html entities with non accentuated characters
		$str = preg_replace('#&([A-Za-z])(?:acute|grave|cedil|circ|orn|ring|slash|th|tilde|uml);#', '\1', $str);
		// replace ligature: Œ, Æ ...
		$str = preg_replace('#&([A-Za-z]{2})(?:lig);#', '\1', (string)$str);
		// delete the rest
		// $str = preg_replace('#&[^;]+;#', '', $str);
		return $str;
	}
}
