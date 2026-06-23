<?php

declare(strict_types=1);

namespace App\Controller;

use IDCT\NATS\Core\NatsClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private NatsClient $client)
    {
    }

    #[Route('/', methods: ['GET'])]
    public function index(): Response
    {
        $kv = $this->client->jetStream()->keyValue('nutprices');
        $prices = [];
        foreach ($kv->getAll()->await() as $nut => $raw) {
            $data = json_decode($raw, true);
            if ($data !== null) {
                $prices[$nut] = $data;
            }
        }
        ksort($prices);

        return $this->render('home.html.twig', ['prices' => $prices]);
    }
}
