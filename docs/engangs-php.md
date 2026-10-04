# Köra engångs-PHP mot appen — två tinker-fällor

`php artisan tinker --execute` och `tinker < fil.php` går genom psysh, som
har två begränsningar som lätt äter en halvtimme:

1. **psysh parsar rad för rad** och faller på flerradiga `try`/`catch`
   (`Cannot use try without catch or finally`).
2. **Radvis `echo` inuti `foreach` kommer tillbaka mangleat** — psysh ekar
   källraderna blandat med utdata.

Hooken `check-prod-tinker.sh` blockerar skriv-PHP, men **bara mot prod**:
båda registreringarna i `.claude/settings.local.json` är `if`-låsta till
`ssh deploy@brottsplatskartan.se '...tinker --execute=*`. Lokal tinker med
`file_put_contents` går igenom — verifierat 2026-08-24 för både `--execute`
och stdin-formen.

För allt som är mer än en enkel läsfråga: skriv ett fristående skript som
bootar Laravel själv och kör det med `php`, inte `tinker`.

```php
<?php // /tmp/skript.php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ... vanlig PHP här, inga psysh-begränsningar ...
echo json_encode($resultat, JSON_UNESCAPED_UNICODE);
```

```bash
docker compose cp /tmp/skript.php app:/tmp/skript.php
docker compose exec -T app php /tmp/skript.php
```

Behöver du ändå tinker och vill ha strukturerad output tillbaka: echo:a en
enda sträng med markörer (`###J###...###E###`) och plocka ut den med `grep -o`.
