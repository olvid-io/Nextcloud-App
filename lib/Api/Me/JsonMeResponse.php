<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Me;

use JsonSerializable;
use OCA\Olvid\Api\Constants;

class JsonMeResponse implements JsonSerializable {
	public string $signature;
	public string $server;
	public bool $revocationAllowed;
	public bool $transferRestricted;
	public string $apiKey;
	public string $nonce;
	public array $pushTopicNames;
	public array $signedRevocations;
	public int $currentTimestamp;
	// minimumBuildVersions => ["android" => N, "ios" => N]
	public array $minimumBuildVersions;

	public function jsonSerialize(): array {
		return [
			Constants::ME_RESPONSE_SIGNATURE => $this->signature,
			Constants::ME_RESPONSE_API_KEY => $this->apiKey,
			Constants::ME_RESPONSE_SERVER => $this->server,
			Constants::ME_RESPONSE_REVOCATION_ALLOWED => $this->revocationAllowed,
			Constants::ME_RESPONSE_TRANSFER_RESTRICTED => $this->transferRestricted,
			Constants::ME_RESPONSE_PUSH_TOPICS => $this->pushTopicNames,
			Constants::ME_RESPONSE_NONCE => $this->nonce,
			Constants::ME_RESPONSE_SIGNED_REVOCATIONS => $this->signedRevocations,
			Constants::ME_RESPONSE_CURRENT_TIMESTAMP => $this->currentTimestamp,
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSIONS => $this->minimumBuildVersions,
		];
	}
}
