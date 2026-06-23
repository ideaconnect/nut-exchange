<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\NutTickInput;
use App\Message\NutTick;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class TickController
{
    #[Route('/api/ticks', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] NutTickInput $in,
        MessageBusInterface $bus,
    ): JsonResponse {
        $ts = $in->ts ?: (int) (microtime(true) * 1000);
        $bus->dispatch(new NutTick($in->nut, $in->price, $ts));

        return new JsonResponse(['status' => 'accepted'], 202);
    }
}
