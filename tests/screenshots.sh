#!/usr/bin/env bash
# Takes new screenshots for the screenshots/ directory and SCREENSHOTS.md, of the system running in a fresh Docker container
# with example contacts (from tests/fixtures/screenshots.csv). Needs the same tools as tests/run.sh.
#
#   tests/screenshots.sh
set -euo pipefail

cd "$(dirname "$0")/.."

IMAGE="${IMAGE:-address-book:screenshots}"
if [ "$IMAGE" = "address-book:screenshots" ]; then
	echo "Building $IMAGE"
	docker build -q -t "$IMAGE" . >/dev/null
fi

CONTAINER="address-book-screenshots-$$"
PORT=$(python3 -c 'import socket; s = socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1])')
export BASE_URL="http://127.0.0.1:$PORT/"
trap 'docker rm -f "$CONTAINER" >/dev/null 2>&1 || true' EXIT
docker run -d --name "$CONTAINER" -p "127.0.0.1:$PORT:80" -e SITE_URL="$BASE_URL" -e TIMEZONE=America/New_York "$IMAGE" >/dev/null
for _ in $(seq 1 60); do
	curl -sf -o /dev/null "${BASE_URL}css/main.css" && break
	sleep 1
done

rm -f screenshots/*.png
export OUTPUT="$PWD/screenshots"
(cd tests/ui && node screenshots.mjs)
echo "Screenshots saved in screenshots/"
