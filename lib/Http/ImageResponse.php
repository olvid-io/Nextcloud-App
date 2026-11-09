<?php

declare(strict_types=1);

namespace OCA\Olvid\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

/**
 * Raw JPEG HTTP response, used to serve avatar images.
 *
 * @template-extends Response<Http::STATUS_OK, array<string, mixed>>
 */
class ImageResponse extends Response {
	public function __construct(
		private readonly string $data,
	) {
		parent::__construct();
		$this->addHeader('Content-Type', 'image/jpeg');
		$this->setStatus(Http::STATUS_OK);
	}

	public function render(): string {
		return $this->data;
	}
}
