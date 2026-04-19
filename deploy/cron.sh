#!/bin/bash
# Host-cron för brottsplatskartan. Installeras i root crontab:
#
#   * * * * * /opt/brottsplatskartan/deploy/cron.sh
#
# Laravel Scheduler (app/Console/Kernel.php) hanterar allt annat –
# crimeevents:fetch, crimeevents:checkForUpdates, ImportVMAAlerts,
# importera-texttv, summary:generate etc. kickas in därifrån.

set -euo pipefail
cd /opt/brottsplatskartan

docker compose exec -T app php artisan schedule:run --no-interaction
