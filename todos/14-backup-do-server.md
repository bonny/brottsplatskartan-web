**Status:** aktiv — inventering klar, redo för backup-körning
**Senast uppdaterad:** 2026-04-24

# Todo #14 — Backup av övriga sajter på gamla DO-servern

## Sammanfattning

Efter Hetzner-cutovern (2026-04-22) ligger Brottsplatskartan på Hetzner,
men DO-droppleten (`dokku.eskapism.se`) kör fortfarande andra sajter.
Innan något händer med den servern (kraschar, glöms bort, stängs ned)
ska en backup tas av det som inte redan är säkrat någon annanstans.

## Inventering (2026-04-24)

### Appar på Dokku

| App                                     | Typ                               | Källkod                                                                                                                  | Status                              |
| --------------------------------------- | --------------------------------- | ------------------------------------------------------------------------------------------------------------------------ | ----------------------------------- |
| `antonblomqvist.se`                     | Statisk (heroku-buildpack-static) | [`bonny/antonblomqvist.se`](https://github.com/bonny/antonblomqvist.se) på GitHub (commit e263cb8 matchar Dokku GIT_REV) | Källkod säker — inget att backa upp |
| `simple-fields`                         | Statisk (heroku-buildpack-static) | [`bonny/simple-fields.com`](https://github.com/bonny/simple-fields.com) på GitHub (commit b860743 matchar Dokku GIT_REV) | Källkod säker — inget att backa upp |
| `brottsplatskartan`                     | Gammal BPK-deploy (stopped)       | Körs på Hetzner                                                                                                          | Skippa                              |
| `brottsplatskartan-tileserver`          | Gammal tileserver (stopped)       | Ersatt av egen på Hetzner                                                                                                | Skippa                              |
| `brottsplatskartan-tyskland-tileserver` | Gammal tileserver (running)       | Ersatt på Hetzner                                                                                                        | Skippa                              |
| `laravel-brottsplatskartan`             | Gammal BPK-deploy (stopped)       | Körs på Hetzner                                                                                                          | Skippa                              |

### Services

| Service                              | Innehåll                             | Åtgärd                                                  |
| ------------------------------------ | ------------------------------------ | ------------------------------------------------------- |
| `mariadb-brottsplatskartan` (10.6.5) | En DB med samma namn (gammal BPK)    | Slut-dump som försäkring — data finns i prod på Hetzner |
| Redis `brottsplatskartan`            | Ephemeral cache/session (gammal BPK) | Skippa — ingen persistent data                          |

### Orphan storage (`/var/lib/dokku/data/storage/`)

| Mapp                             | Storlek     | Kommentar                            | Åtgärd                       |
| -------------------------------- | ----------- | ------------------------------------ | ---------------------------- |
| `eskapism-multisite-uploads`     | 74 MB       | Gammal WP multisite uploads          | **Backa upp** (user-content) |
| `eskapism-multisite-uploads-old` | 4 KB (tomt) | Kvarvarande tom mapp                 | Skippa                       |
| `simple-history-uploads`         | 11 MB       | Plugin-data från gammal installation | **Backa upp**                |

Ingen nuvarande app har bind-mount till dessa (kollat via `dokku storage:list` och `docker inspect`) — de är orphaned från tidigare installations.

## Backup-plan

Två artifacts att hämta, totalt ~100 MB. Engångs-operation, ingen recurring cron.
Destinationen är lokal Mac (`~/Backups/dokku-eskapism-<datum>/`) — därifrån kan
användaren kopiera till extern disk, Hetzner Object Storage eller iCloud efter smak.

### 1. Slut-dump av MariaDB

```bash
ssh dokku.eskapism.se '
    dokku mariadb:export mariadb-brottsplatskartan
' | gzip > ~/Backups/dokku-eskapism-$(date +%Y-%m-%d)/mariadb-brottsplatskartan.sql.gz
```

### 2. Orphan storage-mappar

```bash
ssh dokku.eskapism.se '
    cd /var/lib/dokku/data/storage
    tar czf - eskapism-multisite-uploads simple-history-uploads
' > ~/Backups/dokku-eskapism-$(date +%Y-%m-%d)/storage.tar.gz
```

### 3. Run-book-skript

Se `deploy/backup-dokku-eskapism.sh` — kombinerar ovanstående till en körning.

## Verifiering efter backup

```bash
cd ~/Backups/dokku-eskapism-<datum>

# DB-dump OK?
zcat mariadb-brottsplatskartan.sql.gz | head -20
zcat mariadb-brottsplatskartan.sql.gz | grep -c "^INSERT INTO"

# Storage-arkiv OK?
tar tzf storage.tar.gz | head
du -sh storage.tar.gz mariadb-brottsplatskartan.sql.gz
```

## När kan DO-servern stängas?

Efter körd backup + 1–2 veckors paus för att upptäcka om något saknats.
Koppla ihop med todo #16 (DO-avveckling).

## Status

- [x] Inventering klar (2026-04-24)
- [x] Bekräftat att källkoden för båda statiska sajterna finns på GitHub
- [ ] Kör backup-skriptet (ovan)
- [ ] Verifiera artefakterna
- [ ] Flytta till långtidsarkiv (extern disk / Hetzner Object Storage)
- [ ] Signalera att #16 kan påbörjas
