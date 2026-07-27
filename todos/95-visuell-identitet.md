**Status:** aktiv — kräver designriktning
**Senast uppdaterad:** 2026-07-27

# Todo #95 — Visuell identitet: logotyp, färg, typsnitt

Från art direction-granskningen 2026-07-27.

## Uppmätt

Sajten har ingen visuell identitet utöver innehållet:

- **Loggan är brödtext.** "Brottsplatskartan" i 17 px bold plus grå
  tagline. Inget märke, ingen färg, ingen egen typografi.
- **Färgerna finns men används inte.** `theme-color` är `#ffcc33` (gult)
  och `#0c3256` (mörkblått) finns i SVG-ikonerna, men gult syns bara som
  en 2 px linje ovanför widgets.
- **Inget eget typsnitt.** `-apple-system` rakt igenom. Bra för
  prestanda, noll för varumärke.

Intrycket blir en intern admin-vy snarare än en nyhetstjänst man litar på.
För en sajt vars innehåll är polisrapporter spelar upplevd trovärdighet
roll.

## Vad som behövs

En riktning, inte en implementation: antingen committa till gult +
mörkblått på riktigt (header, H1, aktiv nav, ikonfärger) eller släppa
färgparet och välja något annat. Ett ordmärke eller en enkel symbol till
loggan. Eventuellt ett eget typsnitt för rubriker om prestandabudgeten
tillåter.

## Kräver designriktning

Det här är inte ett fynd som kan implementeras utan ett smakbeslut.
Notera att kartbilder är icke-förhandlingsbara i identiteten — tjänsten
heter Brottsplatskartan.
