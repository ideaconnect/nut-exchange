# 🥜 Nut Exchange

[![Made in the EU](https://raw.githubusercontent.com/ideaconnect/made-in-the-eu/main/software-badge/made-in-the-eu.svg)](https://github.com/ideaconnect/made-in-the-eu)

A real-time price ticker built on **Symfony**, **NATS** (JetStream) and **NUTS** —
following the article
[*It's NUTS: build a dynamic website using Symfony, NATS and NUTS*](https://bpacholek.medium.com/its-nuts-build-a-dynamic-website-using-symfony-nats-and-nuts-d2a928bc10c3).

NATS is the **only** backend service. It plays three roles at once:

| Role            | NATS feature              | Used by                                  |
| --------------- | ------------------------- | ---------------------------------------- |
| Work queue      | `INGEST` stream           | Symfony Messenger (`ingest.ticks`)       |
| Last-value KV   | `nutprices` KV bucket     | Worker write / home page snapshot read   |
| Live event bus  | `EVENTS` stream (`events.>`) | NUTS → browser Server-Sent Events     |

[FrankenPHP](https://frankenphp.dev/) embeds PHP inside Caddy, and the
[`ideaconnect/nuts`](https://github.com/ideaconnect/nuts) Caddy module bridges NATS
subjects to the browser as SSE — so the whole stack is a single web process plus one
worker, with no database, Redis or message broker.

## How it works

```
POST /api/ticks ──► TickController ──► Messenger ──► INGEST stream (ingest.ticks)
                                                          │
                                                          ▼
                                                   NutTickHandler (worker)
                                              ┌───────────┴───────────┐
                                       KV "nutprices"           publish events.prices
                                       (CAS write)                     │
                                                                       ▼
GET /  ──► HomeController ──► KV snapshot          NUTS  ──►  EventSource('/events?topic=prices')
                                                                       │
                                                              browser table updates live
```

- **Snapshot + stream**: `GET /` renders the current prices straight from the KV
  bucket; the page then subscribes via SSE and applies live deltas.
- **Idempotency**: the handler combines a timestamp guard (never overwrite a newer
  value) with KV revision-based compare-and-set, so duplicate / out-of-order
  deliveries and concurrent writers are safe. The JetStream publish uses a
  `msgId` for stream-level dedup.

## Project layout

```
nut-exchange/
├── docker-compose.yml        # nats, nats-init (provisioning), web, worker
├── Caddyfile                 # FrankenPHP + NUTS SSE bridge
├── send.sh                   # post random sample ticks to the API
├── README.md
└── app/                      # Symfony application
    ├── Dockerfile            # builds FrankenPHP with the nuts module via xcaddy
    ├── .env                  # MESSENGER_TRANSPORT_DSN + NATS_URL/USER/PASS
    ├── config/
    │   ├── services.yaml             # NatsClient factory + transport factory tag
    │   └── packages/messenger.yaml   # ingest transport + routing
    ├── templates/home.html.twig
    └── src/
        ├── Dto/NutTickInput.php
        ├── Message/NutTick.php
        ├── Controller/TickController.php
        ├── Controller/HomeController.php
        ├── MessageHandler/NutTickHandler.php
        └── Nats/NatsClientFactory.php
```

## Running

PHP dependencies are installed locally (the `app/` directory is volume-mounted into
the containers). If `app/vendor/` is missing, run `composer install` inside `app/`
first.

```bash
docker compose up --build -d
```

On first start `nats-init` provisions the `EVENTS` stream and the `nutprices` KV
bucket; the `worker` runs `messenger:setup-transports ingest` to create the `INGEST`
stream and its durable consumer, then starts consuming.

Open <http://localhost:8080> and push some prices:

```bash
curl -s -XPOST localhost:8080/api/ticks \
  -H 'Content-Type: application/json' \
  -d '{"nut":"almond","price":12.40}'

curl -s -XPOST localhost:8080/api/ticks \
  -H 'Content-Type: application/json' \
  -d '{"nut":"cashew","price":18.95}'
```

Rows update in real time across every connected browser. Valid nuts are
`almond`, `walnut`, `cashew`, `pecan`, `hazelnut` (see `NutTickInput`).

### Sending sample data

Rather than hand-crafting `curl` calls, [`send.sh`](send.sh) posts random ticks
(random nut + plausible random price) so you can watch the board update live:

```bash
./send.sh            # 20 random ticks, 0.5s apart
./send.sh 100 0.2    # 100 ticks, 0.2s apart
./send.sh 0          # stream forever (Ctrl-C to stop)
BASE_URL=http://host:8080 ./send.sh
```

## Useful commands

```bash
docker compose logs -f worker        # watch message handling

# The nats:alpine server image ships no `nats` CLI — use the nats-box image on the
# compose network (nut-exchange_default) for ad-hoc inspection:
docker run --rm --network nut-exchange_default natsio/nats-box \
  nats -s nats://nats:4222 stream ls
docker run --rm --network nut-exchange_default natsio/nats-box \
  nats -s nats://nats:4222 kv get nutprices almond

docker compose down -v               # stop and wipe NATS data
```
