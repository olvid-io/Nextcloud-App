<?php

declare(strict_types=1);

namespace OCA\Olvid;

/**
 * Shared psalm types for OpenAPI response shapes.
 * Used to document APIs and generate OpenAPI specifications.
 *
 * @psalm-type OlvidMe = array{
 *   firstname: string,
 *   lastname: string,
 *   position: string,
 *   company: string,
 *   useOlvid: bool,
 *   isAdmin: bool,
 * }
 *
 * TODO is this structure really necessary ? Rename to OlvidUserMinimalist ?
 * @psalm-type OlvidUser = array{
 *   id: string,
 *   displayName: string,
 *   useOlvid: bool,
 * }
 *
 * @psalm-type OlvidUserFull = array{
 *   id: string,
 *   displayName: string,
 *   useOlvid: bool,
 *   firstname: string,
 *   lastname: string,
 *   position: string,
 *   company: string,
 * }
 *
 * TODO is this structure really necessary ? Rename to OlvidGroupMinimalist ?
 * @psalm-type OlvidGroup = array{
 *    id: string,
 *    displayName: string,
 *    enabled: bool,
 *    photoUid: string|null,
 *  }
 *
 * @psalm-type OlvidGroupFull = array{
 *   id: string,
 *   displayName: string,
 *   enabled: bool,
 *   customName: string|null,
 *   description: string|null,
 *   photoUid: string|null,
 *   members: list<OlvidUser>,
 * }
 *
 * @psalm-type OlvidGroupRef = array{
 *   id: string,
 *   displayName: string,
 * }
 *
 * ── Directory API ────────────────────────────────────────────────────────────
 *
 * All directory API endpoints return HTTP 200 regardless of success or error.
 * Error responses carry {status: 'error', error: int, message: string}.
 *
 * @psalm-type OlvidDirectoryApiError = array{
 *   status: 'error',
 *   error: int,
 *   message: string,
 * }
 *
 * @psalm-type OlvidDirectoryApiSuccess = array{
 *   status: 'success',
 * }
 *
 * @psalm-type OlvidDirectoryOlvidDiscovery = array{
 *   supportIdentityAuthentication: bool,
 *   apiVersion: int,
 *   minBuildVersions: array{android: int, ios: int, desktop: int, daemon: int},
 * }
 *
 * @psalm-type OlvidDirectoryJwks = array{
 *   keys: list<array{kty: string, crv: string, x: string, y: string, use: string, kid: string, alg: string}>,
 * }
 *
 * @psalm-type OlvidDirectoryOpenIdConfiguration = array{
 *   issuer: string,
 *   authorization_endpoint: string,
 *   token_endpoint: string,
 *   userinfo_endpoint: string,
 *   jwks_uri: string,
 *   scopes_supported: list<string>,
 *   response_types_supported: list<string>,
 *   response_modes_supported: list<string>,
 *   grant_types_supported: list<string>,
 *   acr_values_supported: list<string>,
 *   subject_types_supported: list<string>,
 *   id_token_signing_alg_values_supported: list<string>,
 *   token_endpoint_auth_methods_supported: list<string>,
 *   display_values_supported: list<string>,
 *   claim_types_supported: list<string>,
 *   claims_supported: list<string>,
 *   end_session_endpoint: string,
 * }
 *
 * @psalm-type OlvidDirectoryMeResponse = array{
 *   signature: string,
 *   'api-key': string|null,
 *   server: string,
 *   'revocation-allowed': bool,
 *   'transfer-restricted': bool,
 *   'min-build-versions': array{android: int, ios: int},
 *   'push-topics': list<string>|null,
 *   nonce?: string,
 *   'signed-revocations': list<string>|null,
 *   'current-timestamp': int,
 * }
 *
 * @psalm-type OlvidDirectoryGetMagicSessionResponse = array{
 *   access_token: string,
 *   expires_in: int,
 *   token_type: 'Bearer',
 *   refresh_token: null,
 * }
 *
 * @psalm-type OlvidDirectoryGetKeyResponse = array{
 *   signature: string|null,
 * }
 *
 * @psalm-type OlvidDirectorySearchResponse = array{
 *   results: list<mixed>,
 *   resultsUnactivatedUsers: int,
 *   count: int,
 *   countUnactivatedUsers: int,
 * }
 *
 * @psalm-type OlvidDirectoryListUsersResponse = array{
 *   users: list<mixed>,
 *   timestamp: int,
 * }
 *
 * @psalm-type OlvidDirectoryGroupsResponse = array{
 *   signed_group_blobs: list<string>,
 *   signed_group_deletions: list<string>,
 *   signed_group_kicks: list<string>,
 *   current_timestamp: int,
 * }
 */
class ResponseDefinitions {
}
