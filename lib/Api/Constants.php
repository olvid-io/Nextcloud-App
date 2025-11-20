<?php

namespace OCA\Olvid\Api;

class Constants {
	/*
	 * OIDC client configuration
	 */
	public const OIDC_CLIENT_NAME = "olvid";
	public const OIDC_REDIRECT_URIS = ["https://openid-redirect.olvid.io/","https://openid-redirect.dev.olvid.io/"];
	public const OIDC_ALGORITHM = "RS256";
	public const OIDC_TYPE = "confidential";
	public const OIDC_FLOW_TYPE = "code";
	public const OIDC_TOKEN_TYPE = "opaque";
	public const OIDC_ALLOWED_SCOPES = "";
	public const OIDC_EMAIL_REGEXP = "";

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
	public const USER_ATTRIBUTE_OLVID_COMPANY = "olvid-company";
	public const USER_ATTRIBUTE_OLVID_POSITION = "olvid-position";
	public const USER_ATTRIBUTE_OLVID_IDENTITY = "olvid-identity";
	public const USER_ATTRIBUTE_OLVID_API_KEY = "olvid-api-key";
	public const USER_ATTRIBUTE_OLVID_NONCE = "olvid-nonce";
	public const USER_ATTRIBUTE_OLVID_SIGNED_DETAILS = "olvid-signed-details";
	public const USER_ATTRIBUTE_OLVID_ROLE = "olvid-role";
	public const USER_ATTRIBUTE_OLVID_FULL_SEARCH_FIELD = "olvid-search";
	public const USER_ATTRIBUTE_OLVID_IS_BOT = "olvid-is-bot";

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
