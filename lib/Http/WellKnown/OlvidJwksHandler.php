<?php

declare(strict_types=1);

namespace OCA\Olvid\Http\WellKnown;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Http\WellKnown\GenericResponse;
use OCP\Http\WellKnown\IHandler;
use OCP\Http\WellKnown\IRequestContext;
use OCP\Http\WellKnown\IResponse;
use OCP\IAppConfig;

// TODO TODEL ?
class OlvidJwksHandler implements IHandler {
	private IAppConfig $appConfig;

	public function __construct(IAppConfig $appConfig) {
		$this->appConfig = $appConfig;
	}

	public function handle(string $service, IRequestContext $context, ?IResponse $previousResponse): ?IResponse {
		if ($service === 'jwks') {
			$publicKey = $this->appConfig->getValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_PUBLIC_KEY);

			$jwk = [
				"kty" => "OKP",
				"crv" => "Ed25519",
				"x" => $publicKey,
				"use" => "sig",
				"alg" => "EdDSA",
				// TODO do not hardcode id
				"kid" => "olvid"
			];
			$jwks = [
				"keys" => [
					$jwk
				]
			];
			return new GenericResponse(new JSONResponse($jwks));
		}
		else {
			return $previousResponse;
		}
	}
}
