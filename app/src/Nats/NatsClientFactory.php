<?php

declare(strict_types=1);

namespace App\Nats;

use IDCT\NATS\Connection\NatsOptions;
use IDCT\NATS\Core\NatsClient;

final class NatsClientFactory
{
    public function __construct(
        private string $url,
        private string $user,
        private string $pass,
    ) {
    }

    public function create(): NatsClient
    {
        $client = new NatsClient(new NatsOptions(
            servers: [$this->url],
            username: $this->user,
            password: $this->pass,
        ));
        $client->connect()->await();

        return $client;
    }
}
