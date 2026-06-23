#!/usr/bin/env bash
#
# send.sh — post random nut price ticks to the Nut Exchange API.
#
# Usage:
#   ./send.sh                      # send 20 random ticks, 0.5s apart
#   ./send.sh 100                  # send 100 ticks
#   ./send.sh 100 0.2              # 100 ticks, 0.2s apart
#   ./send.sh 0                    # send forever (Ctrl-C to stop)
#   BASE_URL=http://host:8080 ./send.sh
#
# Watch http://localhost:8080 and the rows will flash as ticks arrive.

set -euo pipefail
export LC_ALL=C   # force '.' as the decimal separator for awk/printf

BASE_URL="${BASE_URL:-http://localhost:8080}"
COUNT="${1:-20}"     # number of ticks to send; 0 = run forever
DELAY="${2:-0.5}"    # seconds between ticks

# Valid nuts (must match App\Dto\NutTickInput::NUTS)
NUTS=(almond walnut cashew pecan hazelnut)

# Rough base price (in cents) per nut, for plausible-looking numbers.
declare -A BASE=( [almond]=1240 [walnut]=910 [cashew]=1895 [pecan]=2100 [hazelnut]=1500 )

post() {
  local nut="$1" price="$2" code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 \
    -X POST "$BASE_URL/api/ticks" \
    -H 'Content-Type: application/json' \
    -d "{\"nut\":\"$nut\",\"price\":$price}" || echo "000")
  printf '  %-9s %9s  -> HTTP %s\n' "$nut" "$price" "$code"
}

# Quick reachability check.
if ! curl -s -o /dev/null --max-time 5 "$BASE_URL/"; then
  echo "warning: $BASE_URL is not reachable — is 'docker compose up' running?" >&2
fi

echo "Sending random ticks to $BASE_URL/api/ticks (count=$([ "$COUNT" = 0 ] && echo '∞' || echo "$COUNT"), delay=${DELAY}s)"

i=0
while :; do
  nut="${NUTS[RANDOM % ${#NUTS[@]}]}"
  base="${BASE[$nut]}"
  # Random walk: +/- up to ~8% of the base price (in cents).
  span=$(( base / 6 + 1 ))
  jitter=$(( RANDOM % span - span / 2 ))
  cents=$(( base + jitter ))
  (( cents < 1 )) && cents=1
  price=$(awk "BEGIN { printf \"%.2f\", $cents / 100 }")

  post "$nut" "$price"

  i=$(( i + 1 ))
  if [ "$COUNT" != "0" ] && [ "$i" -ge "$COUNT" ]; then
    break
  fi
  sleep "$DELAY"
done

echo "Done. Sent $i tick(s)."
