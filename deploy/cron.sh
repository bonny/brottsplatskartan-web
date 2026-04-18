#!/bin/bash
# Host-cron för brottsplatskartan. Installeras i root crontab:
#
#   * * * * * /opt/brottsplatskartan/deploy/cron.sh schedule
#   */12 * * * * /opt/brottsplatskartan/deploy/cron.sh fetch
#   */33 * * * * /opt/brottsplatskartan/deploy/cron.sh check-updates

set -euo pipefail
cd /opt/brottsplatskartan

case "${1:-}" in
	schedule)
		docker compose exec -T app php artisan schedule:run --no-interaction
		;;
	fetch)
		docker compose exec -T app php artisan crimeevents:fetch
		;;
	check-updates)
		docker compose exec -T app php artisan crimeevents:checkForUpdates
		;;
	*)
		echo "Usage: $0 {schedule|fetch|check-updates}" >&2
		exit 1
		;;
esac
