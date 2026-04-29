**Status:** klar 2026-04-29 — droppleten destroyad och DO-kontot uppsagt (soak hoppades över)
**Senast uppdaterad:** 2026-04-29

# Todo #16 — Rensa / avveckla gamla DO-servern (Dokku)

## Sammanfattning

Efter Hetzner-cutovern (2026-04-22) ligger Brottsplatskartan på Hetzner.
DO-droppleten (`dokku.eskapism.se`) kör fortfarande andra sajter och
betalas i onödan. När backup av övriga appar är säkrad (todo #14) ska
servern avvecklas.

## Förutsättning

- **#14 måste vara klar först.** Alla Dokku-appar + DBs backade upp
  och verifierade (restore-test, inte bara "dumpen finns").

## Avvecklingssteg

1. ✅ **Flytta kvarvarande appar** — antonblomqvist.se och simple-fields.com
   live på BPK-Hetzner sedan 2026-04-26 (todo #21 klar).
2. ✅ **DNS:** records som pekade på DO-IP:n `138.68.89.224` är
   ompekade till Hetzner 2026-04-29 (antonblomqvist.se, simple-fields.com
   + www.\*). Inga BPK-domäner pekar längre på DO.
3. ✅ **Stoppa Dokku-apparna** — `dokku apps:stop --all` kört 2026-04-25
4. ✅ **Verifiera att inget trafikerar servern** — djupdyk i nginx
   access-loggar 2026-04-29: inga 2xx-svar mot någon app de senaste
   dygnen. Bara scanners/AI-crawlers som får 502/400. Inga glömda
   beroenden. (`antonblomqvist.se` på Hetzner-IP svarar 200 OK.)
5. ✅ **Stäng av droppleten** — power off via DO-konsolen 2026-04-29.
   Servern går att starta igen om något kritiskt dyker upp under soak.
6. **Radera droppleten** via DO-konsolen efter soak (~2026-05-13,
   ca 2 veckor avstängd utan att något saknats). Snapshot först som
   sista utväg om man vill kunna återskapa.
7. **Avsluta/nedgradera eventuella DO-tillägg** (block storage,
   floating IPs, Spaces, reserved IPs)

## Risker

- **Glömda beroenden:** någon extern sajt/tjänst/cron kan anropa
  `*.eskapism.se` som ligger på DO. Tail access-loggarna minst
  7 dagar innan avveckling
- **DNS-records kvar:** gamla A-records som pekar på död IP ger
  fula fel. Sweep:a Loopia efter records som pekar på DO-IP:n
- **Email/MX:** verifiera att inga MX-records pekar på servern

## När

Droppleten avstängd 2026-04-29 (power off, inte raderad). Soak ~2 veckor
i avstängt läge så vi kan starta upp igen om något oförutsett saknas.
Rimlig tidpunkt för slutlig radering: **från ~2026-05-13** och framåt.

## Relaterat

- #14: backup av övriga sajter (blocker)
- Memory: `reference_old_do_server.md` har SSH-tillgång till
  `dokku.eskapism.se`
