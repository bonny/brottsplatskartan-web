**Status:** aktiv (designfas)
**Senast uppdaterad:** 2026-04-27

# Todo #34 — Långa event-slugs (URL:er som innehåller hela brödtexten)

## Problem

Events skapade efter 2022-02-11 får URL:er där hela `description`
bakas in i sluggen. Resultatet är extremt långa URL:er på 100+ tecken.

Exempel:

```
https://brottsplatskartan.se/stockholm/stold-stockholm-sodermalm-
en-man-pa-sodermalm-greps-misstankt-for-stold-efter-att-ha-
tillgripit-en-cykel-500777
```

Slug-konstruktionen i `CrimeEvent::getPermalink()`:

1. `parsed_title` (t.ex. "Stöld")
2. `parsed_title_location` (t.ex. "Stockholm Södermalm")
3. Prio 1-locations
4. **`description` som plain text** — det här är boven (rad 269-271)
5. ID

Description-delen lades till 2022-02-11 (commit-meddelande
"Endast för länkar skapade efter 2022-02-11 så inte gamla
länkar påverkas") — sannolikt för SEO-keyword-rikedom, men
har skapat URL-svans-problem.

## Varför detta är ett problem 2026

- **SEO:** Google-rekommendationer säger 60–80 tecken som ideal
  URL-längd. Långa slugs trunkeras i SERP-snippets, klickbenägenheten
  sjunker. Helpful Content System värderar ren URL-struktur högt 2026.
- **Delning:** långa URL:er klipps i SMS/Twitter/Slack och ser
  spammiga ut. Risk för minskad organisk delning.
- **AI-crawlers:** vissa AI-crawlers (GPTBot, ClaudeBot) deprioriterar
  eller trunkerar långa URL:er vid citering.
- **Bandbredd och rendering:** små vinster i HTML-storlek när events
  länkas i listor (t.ex. mest-läst, plats-sidor, sitemap).
- **Användare-osäkerhet:** "Är denna URL legitimt?" — långa URL:er
  med många dashes triggar phishing-känsla hos användare.

## Vad vi behöver bestämma

### 1. Vilket slug-format ska vi ha framöver?

| Format                                         | Exempel                                               | Tecken |
| ---------------------------------------------- | ----------------------------------------------------- | -----: |
| `{type}-{location}-{id}` (kort)                | `/stockholm/stold-sodermalm-500777`                   |    ~30 |
| `{type}-{location}-{firstNwords}-{id}`         | `/stockholm/stold-sodermalm-en-man-greps-500777`      |    ~50 |
| `{type}-{location}-{description}-{id}` (idag)  | `/stockholm/stold-sodermalm-en-man-på-sodermalm-...`  |   100+ |
| `{id}` (extremt kort)                          | `/stockholm/500777`                                   |     12 |

**Rekommendation:** `{type}-{location}-{id}` — drar ned till ~30 tecken
utan att tappa SEO-värde (huvudkeyword + plats finns kvar). Description
flyttas helt till `<title>` + `<meta name="description">` + brödtext.

### 2. Hur hantera befintliga långa URL:er?

Två strategier:

**A. 301-redirect alla gamla event-URL:er till nya korta**
- Kräver att `singleEvent`-routen accepterar både gamla och nya format
- Lookup på ID i URL:n (sista numeriska delen)
- Pro: ren URL-struktur framöver
- Con: ~3 år av events × 200k+ events = stor SEO-påverkan; 301:er kostar
  något i ranking-equity

**B. Behåll gamla URL:er, ändra bara format för nya events**
- Pragmatiskt — minimal påverkan på existerande SEO
- Pro: ingen 301-arbete, ingen risk för ranking-tapp
- Con: blandad slug-stil i URL-strukturen tills gamla events naturligt
  fasas ut (eller markeras noindex via #29)

**Rekommendation: B (gradvis migration).** Kombinera med #29 — gamla
tunna events markeras redan noindex, så lång-URL-problemet löser sig
över tid utan migrations-risk.

### 3. Truncation-regler

Om vi behåller description i sluggen men begränsar:
- Max ~80 tecken total slug-length före `-{id}`?
- Truncate på ord-gräns (inte mitt i ett ord)?
- Vad händer med edge cases där location är lång (t.ex. "norra-storstockholm-norrtaljekommun")?

## Förslag på plan

1. **Mät påverkan först.** Hur många events har URL längre än 80 tecken?
   Genomsnittlig längd före och efter 2022-02-11?
2. **Designa nytt slug-format** baserat på data — sannolikt
   `{type}-{location}-{id}` med kort version av location.
3. **Implementera bara för nya events** — `getPermalink()` checkar om
   datum >= migrationsdatum → ny formel.
4. **Verifiera SEO-stabilitet** efter 30 dagar via GSC.
5. **Vid behov:** 301-migration för historiska events (om data visar
   att gamla URL:er förlorar trafik).

## Risker

| Risk                                   | Mitigering                                       |
| -------------------------------------- | ------------------------------------------------ |
| 301-kedjor om gamla URL:er flyttas     | Verifiera ETT hopp via `curl -ILv`               |
| Ranking-tapp på indexerade event-URL:er | Pilot på liten subset först + GSC-mätning        |
| Kollisioner (samma slug, olika IDs)    | ID-suffix garanterar unikhet — som idag          |
| Sociala delningar bryts                | URL:er behåller ID-suffix → 301-mappning möjlig  |

## Beroenden

- Synergi med #29 (audit indexerade pages) — gamla tunna events
  markeras redan noindex, så långa URL:er försvinner naturligt
  från Google.
- Synergi med #32 (schema-sweep) — `NewsArticle.headline` lagrar
  full titel utanför URL, så ingen SEO-keyword-förlust när vi
  kortar slug.

## Confidence

**Medel.** Ändringen är teknisk-trivial, men SEO-strategin
(migration vs. gradvis) kräver datadrivet beslut.
