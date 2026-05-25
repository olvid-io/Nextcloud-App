<?php

namespace OCA\Olvid\Api;

class Constants {
	public const DEFAULT_OLVID_SERVER = "https://server.olvid.io";

	/*
	 ** Session and authentication
	 */
	public const IDENTITY_SESSION_DURATION_S = 3600 * 24; // 1 day
	public const MAGIC_SESSION_DURATION_S = 3600; // 1 hour
	public const MAGIC_LINK_DURATION_S = 300; // 5 min // TODO implements

	// Minimum build numbers supporting this version of the keycloak plugin API
	public const MIN_BUILD_ANDROID = 200;
	public const MIN_BUILD_IOS = 650;
    public const MIN_BUILD_DAEMON = 200000;
    public const MIN_BUILD_DESKTOP = 20000;
	public const OLVID_DIRECTORY_API_VERSION = 1;

	/*
	 * Engine API (binary Encoded protocol — requestChallenge / getSession)
	 */
	public const ENGINE_NONCE_LENGTH            = 32;
	public const ENGINE_CHALLENGE_LENGTH        = 32;
	public const ENGINE_RESPONSE_LENGTH         = 80;   // 16 prefix + 32 SHA-256 hash + 32 y scalar
	public const ENGINE_RESPONSE_LENGTH_SHA512  = 112;  // 16 prefix + 64 SHA-512 hash + 32 y scalar
	// Prefix prepended to the challenge before signing: "keycloakChallenge"
	public const ENGINE_TOKEN_SIGNATURE_PREFIX  = "keycloakChallenge";

	/*
	 * OlvidUserDetailsKey
	 */
	public const DETAILS_KEY_ID = "id";
	public const DETAILS_KEY_IDENTITY = "identity";
	public const DETAILS_KEY_FIRST_NAME = "first-name";
	public const DETAILS_KEY_LAST_NAME = "last-name";
	public const DETAILS_KEY_POSITION = "position";
	public const DETAILS_KEY_COMPANY = "company";
	public const DETAILS_KEY_TIMESTAMP = "timestamp";

	/*
	 * User attributes keys in Config
	 */
	public const USER_ATTRIBUTE_OLVID_FIRSTNAME = "olvid-firstname";
	public const USER_ATTRIBUTE_OLVID_LASTNAME = "olvid-lastname";
	public const USER_ATTRIBUTE_OLVID_COMPANY = "olvid-company";
	public const USER_ATTRIBUTE_OLVID_POSITION = "olvid-position";
	public const USER_ATTRIBUTE_OLVID_IDENTITY = "olvid-identity";
	public const USER_ATTRIBUTE_OLVID_API_KEY = "olvid-api-key";
	public const USER_ATTRIBUTE_OLVID_NONCE = "olvid-nonce";
	public const USER_ATTRIBUTE_OLVID_SIGNED_DETAILS = "olvid-signed-details";
	public const USER_ATTRIBUTE_OLVID_ROLE = "olvid-role";
	public const USER_ATTRIBUTE_OLVID_FULL_SEARCH_FIELD = "olvid-search";
	public const USER_ATTRIBUTE_OLVID_IS_BOT = "olvid-is-bot";
	public const USER_ATTRIBUTE_OLVID_MAGIC_TOKEN = "olvid-magic-token";
	// when set, any token issued before this date is considered as invalid
	public const USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE = 'olvid-session-revoked-before';
	/*
	 * Api request and response field names
	 */
	// /me constants
	public const ME_REQUEST_TIMESTAMP = "timestamp";
	public const ME_REQUEST_DEVICE_UID = "deviceUid";
	public const ME_RESPONSE_SIGNATURE = "signature";
	public const ME_RESPONSE_API_KEY = "api-key";
	public const ME_RESPONSE_SERVER = "server";
	public const ME_RESPONSE_REVOCATION_ALLOWED = "revocation-allowed";
	public const ME_RESPONSE_TRANSFER_RESTRICTED = "transfer-restricted";
	public const ME_RESPONSE_PUSH_TOPICS = "push-topics";
	public const ME_RESPONSE_NONCE = "nonce";
	public const ME_RESPONSE_SIGNED_REVOCATIONS = "signed-revocations";
	public const ME_RESPONSE_CURRENT_TIMESTAMP = "current-timestamp";
	public const ME_RESPONSE_MINIMUM_BUILD_VERSIONS = "min-build-versions";
	public const ME_RESPONSE_MINIMUM_BUILD_VERSION_ANDROID_LABEL = "android";
	public const ME_RESPONSE_MINIMUM_BUILD_VERSION_IOS_LABEL = "ios";
	public const MY_DEVICES_DEVICES = "devices";
	public const MY_DEVICES_DEVICE_UID = "deviceUid";
	public const MY_DEVICES_PLATFORM = "platform";
	public const MY_DEVICES_UNKNOWN = "unknown";

	// /getKey constants
	public const GET_KEY_REQUEST_USER_ID = "user-id";
	public const GET_KEY_RESPONSE_SIGNATURE = "signature";

	// /putKey constants
	public const PUT_KEY_REQUEST_IDENTITY = "identity";

	// /search constants
	public const SEARCH_REQUEST_FILTER = "filter";
	public const SEARCH_COUNT_LIMIT = 50;
	public const OLD_DEVICE_TIME_LIMIT = 30;
	public const KEYCLOAK_SEARCH_COUNT_LIMIT = 10;
	public const SEARCH_RESPONSE_RESULTS = "results";
	public const SEARCH_RESPONSE_RESULTS_UNACTIVATED_USERS = "resultsUnactivatedUsers";
	public const SEARCH_RESPONSE_COUNT = "count";
	public const SEARCH_RESPONSE_COUNT_UNACTIVATED_USERS = "countUnactivatedUsers";

	// /verify constants
	public const VERIFY_REQUEST_SIGNATURE = "signature";

	// /transferProof constants
	public const TRANSFER_PROOF_REQUEST_SESSION_ID = "session_id";
	public const TRANSFER_PROOF_REQUEST_SAS = "sas";
	public const TRANSFER_PROOF_RESPONSE_SIGNATURE = "signature";

	// /revocationTest constants
	public const REVOCATION_TEST_REQUEST_NONCE = "nonce";

	// /magic constants
	public const MAGIC_REQUEST_USERNAME = "username";
	public const MAGIC_REQUEST_NONCE = "nonce";
	public const MAGIC_RESPONSE_TOKEN = "token";

	// /getMagicSession constants
	public const GET_MAGIC_SESSION_REQUEST_USERNAME = "username";
	public const GET_MAGIC_SESSION_REQUEST_TOKEN = "token";

   // /groups constants
	public const GROUPS_REQUEST_TIMESTAMP = "timestamp";
	public const GROUPS_RESPONSE_SIGNED_GROUP_BLOBS = "signed_group_blobs";
	public const GROUPS_RESPONSE_SIGNED_GROUP_DELETIONS = "signed_group_deletions";
	public const GROUPS_RESPONSE_SIGNED_GROUP_KICKS = "signed_group_kicks";
	public const GROUPS_RESPONSE_CURRENT_TIMESTAMP = "current_timestamp";


	// /listUsers
	public const LIST_USERS_REQUEST_TIMESTAMP = "timestamp";
	public const LIST_USERS_RESPONSE_USERS = "users";
	public const LIST_USERS_RESPONSE_TIMESTAMP = "timestamp";
}
