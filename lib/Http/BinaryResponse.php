<?php

declare(strict_types=1);

namespace OCA\Olvid\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

/** Raw binary (application/octet-stream) HTTP response for the Olvid Engine API. */
class BinaryResponse extends Response {
    public function __construct(private readonly string $data) {
        parent::__construct();
        $this->addHeader('Content-Type', 'application/octet-stream');
        $this->setStatus(Http::STATUS_OK);
    }

    public function render(): string {
        return $this->data;
    }
}
