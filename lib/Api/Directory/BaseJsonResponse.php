<?php

namespace OCA\Olvid\Api\Directory;

use JsonSerializable;

class BaseJsonResponse implements JsonSerializable
{
    public const STATUS_ERROR = "error";
    public const STATUS_SUCCESS = "success";

    public const ERROR_CODE_NO_ERROR = 0;
    public const ERROR_CODE_INTERNAL_ERROR = 1;
    public const ERROR_CODE_PERMISSION_DENIED = 2;
    public const ERROR_CODE_INVALID_REQUEST = 3;
    public const ERROR_CODE_IDENTITY_ALREADY_UPLOADED = 4;
    public const ERROR_CODE_BAD_REALM_TYPE = 5;
    public const ERROR_CODE_IDENTITY_WAS_REVOKED = 6;
    public const ERROR_CODE_QUERY_NOT_ALLOWED = 7;
    public const ERROR_CODE_IDENTITY_NOT_UPLOADED_YET = 8;


    // --- Properties ---
    public string $status;
    public int $error;
    public string $message;

    // --- Constructor ---
    public function __construct($message, $error, $status) {
		$this->status = $status;
		$this->error = $error;
		$this->message = $message;
	}

	public function jsonSerialize(): array {
		return [
			"status" => $this->status,
			"error" => $this->error,
			"message" => $this->message,
		];
	}
}
