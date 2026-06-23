<?php

return [
	'routes' => [
		[
			'name' => 'app#home',
			'url' => '/',
			'verb' => 'GET',
		],
	],
	'ocs' => [
		[
			'name' => 'ocs#olvid',
			'url' => '/',
			'verb' => 'GET',
		],
		[
			'name' => 'wellKnown#oidc',
			'url' => '/.well-known/openid-configuration',
			'verb' => 'GET',
		],
		[
			'name' => 'wellKnown#jwks',
			'url' => '/.well-known/jwks',
			'verb' => 'GET',
		],
	]
];
