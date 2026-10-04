# Händelsefiltrering (ContentFilterService) — manuell körning

Filtreringen körs automatiskt vid `crimeevents:fetch`. Kör den manuellt för
att omvärdera äldre händelser:

```bash
# Dry-run
docker compose exec app php artisan crimeevents:check-publicity --since=365

# Applicera
docker compose exec app php artisan crimeevents:check-publicity --apply --since=365
```
