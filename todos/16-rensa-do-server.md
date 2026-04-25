**Status:** aktiv — apparna stoppade, väntar på soak innan radering
**Senast uppdaterad:** 2026-04-25

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

1. **Flytta kvarvarande appar** (om några ska leva vidare) till ny host
   — eller bekräfta att de ska läggas ned permanent — _antonblomqvist.se
   och simple-fields är statiska sajter med källkod på GitHub; läggs ner
   på DO och hostas vid behov någon annanstans_
2. **DNS:** ta bort/uppdatera records som pekar på DO-IP:n `138.68.89.224`
   (Loopia)
3. ✅ **Stoppa Dokku-apparna** — `dokku apps:stop --all` kört 2026-04-25
4. **Verifiera att inget trafikerar servern** — tail access-loggar
   någon dag för att upptäcka glömda sub-domäner eller externa
   beroenden. _Direkt efter stop-kommandot 2026-04-25: bara förväntade
   sajter slutade fungera (antonblomqvist.se, simple-fields.com).
   Inga okända beroenden upptäckta._
5. **Radera droppleten** via DO-konsolen (eller snapshot först som
   sista utväg, kan raderas efter några veckor)
6. **Avsluta/nedgradera eventuella DO-tillägg** (block storage,
   floating IPs, Spaces, reserved IPs)

## Risker

- **Glömda beroenden:** någon extern sajt/tjänst/cron kan anropa
  `*.eskapism.se` som ligger på DO. Tail access-loggarna minst
  7 dagar innan avveckling
- **DNS-records kvar:** gamla A-records som pekar på död IP ger
  fula fel. Sweep:a Loopia efter records som pekar på DO-IP:n
- **Email/MX:** verifiera att inga MX-records pekar på servern

## När

Inte brådskande. Tidigast efter:

- 2 veckor på Hetzner utan incidenter (safety net — rollback-fönster)
- Todo #14 klar och restore-testad

Rimlig tidpunkt: **från ~2026-05-15** och framåt.

## Relaterat

- #14: backup av övriga sajter (blocker)
- Memory: `reference_old_do_server.md` har SSH-tillgång till
  `dokku.eskapism.se`
