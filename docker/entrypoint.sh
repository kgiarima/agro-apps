#!/bin/sh
set -eu

until php bin/console db:migrate; do
  echo "Database unavailable; retrying in 2 seconds..." >&2
  sleep 2
done

exec "$@"
