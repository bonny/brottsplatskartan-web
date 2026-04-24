# Todo #14 — Backup av övriga sajter på gamla DO-servern

## Sammanfattning

Efter Hetzner-cutovern (2026-04-22) ligger Brottsplatskartan på Hetzner, men
DO-droppleten (`dokku.eskapism.se`) kör fortfarande andra sajter. Innan
något händer med den servern (kraschar, glöms bort, eller stängs ned)
bör en backup tas av de kvarvarande apparna.

## Innehåll att backa upp

Kräver inventering. Troliga kandidater nämnda tidigare:
- antonblomqvist.se
- simple-fields
- eventuella fler Dokku-appar + DBs

## Föreslagen plan

### 1. Inventering
```bash
ssh dokku.eskapism.se
dokku apps:list
dokku mariadb:list
dokku mysql:list 2>/dev/null
dokku postgres:list 2>/dev/null
dokku redis:list 2>/dev/null
du -sh /var/lib/dokku/data/storage/*
```

### 2. Script som backar upp allt
- Dumpar alla DBs (gzip:ade)
- tar.gz:ar persistent storage per app
- Metadata: `dokku config:export <app>` för env-vars
- Samlar i en mapp per app

### 3. Backup-destinationer att välja mellan
- Lokal extern disk (enklast, engångs)
- Hetzner Object Storage (samma som BPK använder)
- Alternativt iCloud/Dropbox-syncad mapp
- Återkommande (cron) vs engångs

## Öppna frågor

- Ska apparna flyttas till Hetzner också, eller bara arkiveras?
- Hur länge ska DO-servern leva?
- Vilken backup-retention behövs (1 snapshot räcker? Rullande?)

## Status

Ej påbörjat. Låg prio men viktigt innan DO-servern stängs av eller
glöms bort. Gör innan årsskiftet.
