<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NutTick;
use IDCT\NATS\Core\NatsClient;
use IDCT\NATS\Exception\JetStreamException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class NutTickHandler
{
    public function __construct(private NatsClient $client)
    {
    }

    public function __invoke(NutTick $tick): void
    {
        $kv    = $this->client->jetStream()->keyValue('nutprices');
        $value = json_encode(['price' => $tick->price, 'ts' => $tick->ts]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $entry  = $kv->get($tick->nut)->await();
            $stored = $entry && $entry->value ? json_decode($entry->value, true) : null;

            if ($stored !== null && $tick->ts <= ($stored['ts'] ?? 0)) {
                return;
            }

            try {
                if ($entry === null) {
                    $kv->createKey($tick->nut, $value)->await();
                } else {
                    $kv->update($tick->nut, $value, $entry->revision ?? 0)->await();
                }
            } catch (JetStreamException) {
                continue;
            }

            $this->client->jetStream()->publish(
                'events.prices',
                json_encode(['nut' => $tick->nut, 'price' => $tick->price, 'ts' => $tick->ts]),
                msgId: $tick->nut . ':' . $tick->ts,
            )->await();

            return;
        }

        throw new \RuntimeException("CAS contention on {$tick->nut}");
    }
}
