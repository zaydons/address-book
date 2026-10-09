#!/usr/bin/env bash
# Runs all of the tests against the system running in a fresh Docker container.
#
#   tests/run.sh
#
# Needs Docker, Python 3 and Node.js. Set up the test tools once with:
#   pip install -r tests/requirements.txt
#   (cd tests/ui && npm ci && npx playwright install --with-deps chromium)
#
# Optional environment variables:
#   IMAGE        an image to test instead of building one from this directory
#   MYSQL_IMAGE  a MySQL image (such as mysql:9) to also test copying data from MySQL with tools/mysql-to-sqlite.php
#   SKIP_UI=1    skip the browser tests
#   PYTEST_ARGS  extra arguments for pytest, such as "-k contacts"
set -euo pipefail

cd "$(dirname "$0")/.."

export IMAGE="${IMAGE:-address-book:test}"
if [ "$IMAGE" = "address-book:test" ]; then
	echo "Building $IMAGE"
	docker build -q -t "$IMAGE" . >/dev/null
fi

# Start the system with an empty database, on a free port
export APP_CONTAINER="address-book-test-$$"
PORT=$(python3 -c 'import socket; s = socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1])')
export BASE_URL="http://127.0.0.1:$PORT/"
cleanup() {
	status=$?
	if [ $status -ne 0 ]; then
		echo "--- Logs from $APP_CONTAINER ---"
		docker logs "$APP_CONTAINER" 2>&1 | tail -50 || true
	fi
	docker rm -f "$APP_CONTAINER" >/dev/null 2>&1 || true
	exit $status
}
trap cleanup EXIT
docker run -d --name "$APP_CONTAINER" -p "127.0.0.1:$PORT:80" -e SITE_URL="$BASE_URL" -e TIMEZONE=UTC "$IMAGE" >/dev/null

echo "Waiting for $BASE_URL"
for _ in $(seq 1 60); do
	curl -sf -o /dev/null "${BASE_URL}css/main.css" && break
	sleep 1
done

echo "Running the HTTP and database tests"
python3 -m pytest tests ${PYTEST_ARGS:-} -p no:cacheprovider

if [ "${SKIP_UI:-}" != "1" ]; then
	echo "Running the browser tests"
	(cd tests/ui && ADMIN_PASSWORD=TestAdmin123 npm test --silent)
fi

# The system must not have shown any PHP errors while being tested
if docker logs "$APP_CONTAINER" 2>&1 | grep -E "PHP (Warning|Deprecated|Fatal|Notice)"; then
	echo "PHP errors were logged while testing"
	exit 1
fi
echo "All tests passed"
