<?php

declare(strict_types=1);

namespace OCA\Olvid\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

/** Raw JPEG HTTP response, used to serve avatar images. */
class ImageResponse extends Response {
	public function __construct(
		private readonly string $data,
	) {
		parent::__construct();
		$this->addHeader('Content-Type', 'image/jpeg');
		// URL includes a ?v=<photoUid> cache-buster, so the image can be cached aggressively.
		$this->addHeader('Cache-Control', 'public, max-age=31536000, immutable');
		$this->setStatus(Http::STATUS_OK);
	}

	public function render(): string {
		return $this->data;
	}
}
