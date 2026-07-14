<?php

/** @noinspection PhpMultipleClassesDeclarationsInOneFile */

namespace OCA\Olvid\Utils\Context\OlvidServer;

use Exception;

abstract class OlvidServerException extends Exception {
	// local error raised if server api key or url is not set
	public const ERROR_INVALID_REQUEST = 1;
	public const ERROR_INTERNAL = 2;
	public const ERROR_INVALID_API_KEY = 3;
	public const ERROR_API_KEY_NOT_FOUND = 4;
	public const ERROR_MISSING_BOT_PERMISSION = 5;
}

// client side: miss api key or server url in app configuration
class InvalidConfigurationException extends OlvidServerException {
	public function __construct() {
		parent::__construct();
		$this->code = 0;
		$this->message = 'Invalid configuration: missing api key or server url';
	}
}

// client side: cannot parse / use request
class InvalidRequestException extends OlvidServerException {
	public function __construct() {
		parent::__construct();
		$this->code = self::ERROR_INVALID_REQUEST;
		$this->message = 'Invalid request';
	}
}

// client side: cannot contact server
class NetworkException extends OlvidServerException {
	public function __construct(string $message) {
		parent::__construct();
		$this->code = 0;
		$this->message = $message;
	}
}

// error server side
class InternalErrorException extends OlvidServerException {
	public function __construct() {
		parent::__construct();
		$this->code = self::ERROR_INTERNAL;
		$this->message = 'Internal olvid server error';
	}
}

// olvid server api key (keycloak api key) passed in query is not valid
class InvalidApiKeyException extends OlvidServerException {
	public function __construct() {
		parent::__construct();
		$this->code = self::ERROR_INVALID_API_KEY;
		$this->message = 'Olvid server api key not found or invalid';
	}
}

// raised by revokeApiKey if key does not exist in db
class ApiKeyNotFoundException extends OlvidServerException {
	public function __construct() {
		parent::__construct();
		$this->code = self::ERROR_API_KEY_NOT_FOUND;
		$this->message = 'revokeApiKey: api key not found';
	}
}

// unused
class MissingBotPermissionException extends OlvidServerException {
	public function __construct() {
		parent::__construct();
		$this->code = self::ERROR_MISSING_BOT_PERMISSION;
		$this->message = 'Olvid server api key miss Olvid Bot permission';
	}
}
