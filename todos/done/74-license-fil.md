**Status:** klar 2026-05-13 — LICENSE committad (AGPL v3, inte MIT), composer.json uppdaterad, README utökad med Licens-sektion.
**Senast uppdaterad:** 2026-05-13

# Todo #74 — Committa LICENSE-fil (AGPL v3)

## Sammanfattning

`composer.json` deklarerade `"license": "MIT"` men ingen `LICENSE`-fil
fanns i repo-roten. Diskussion runt #73 ledde till val av **AGPL v3 i
stället för MIT** — för att täppa SaaS-luckan (kommersiella aktörer som
hostar modifierade kopior måste släppa sin källkod).

## Bakgrund

Uppdagat i #73 fas 1 IP-research (`tmp-konkurrent-analys/03-ip-research.md`).
Repo har funnits öppet sedan 2014 — alla bidrag (inklusive författarens
egna) har skett under implicit MIT enligt composer.json.

Diskussion kring licensval (#73 chat 2026-05-13):

- MIT (status quo) — max goodwill men noll skydd mot kommersiell
  webb-hosting-kloning.
- GPL v3 — kopplar inte SaaS-användning (känd lucka), i praktiken samma
  som MIT för en webbapp.
- AGPL v3 — täpper SaaS-luckan, fortfarande OSI-godkänd open source,
  passar EU-positionering och oberoende-narrativ.
- "Ingen licens" — sämre än alla alternativ (motstridig signal mot composer.json).

Beslut: **AGPL v3 (or-later)** — FSF-förvaltad, samma juridiska familj som
GPL, används av GitLab CE, Plausible, Mastodon, Nextcloud m.fl.

Notera: licens-byte är **inte retroaktivt**. Alla commits fram till bytet
förblir lagligt MIT — den som klonar gammal version får MIT-rättigheter.
Bara framtida commits är AGPL.

## Vad gjordes (2026-05-13)

1. ✅ Skapat `LICENSE` i repo-roten — kanonisk AGPL v3-text från SPDX
   (235 rader).
2. ✅ Uppdaterat `composer.json` rad 10: `"MIT"` → `"AGPL-3.0-or-later"`.
3. ✅ Lagt till `## Licens`-sektion längst ner i README.md med kort
   förklaring + copyright-rad.
4. Ej genomförda (inga ändringar behövdes):
    - `package.json` — ingen license-rad fanns.
    - Blade-templates / footer — ingen licens-text fanns att uppdatera.
    - `public/llms.txt` — inga licens-omnämnanden.
    - `/sida/om` — sidan existerar inte som Blade-template.

## Verifiering (TODO post-deploy)

- Efter merge: `gh api repos/bonny/brottsplatskartan-web --jq .license`
  bör returnera AGPL-3.0-objektet inom ~1 min.
- GitHub repo-header ska visa "AGPL-3.0 license"-badge.

## Confidence

hög — trivial åtgärd med tydlig juridisk position och noll teknisk risk.
