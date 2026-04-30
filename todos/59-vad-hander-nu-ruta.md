**Status:** aktiv (idé — research saknas)
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #59 — "Vad händer nu"-ruta (Krimkartan/DN Direkt-känsla)

## Sammanfattning

> Krimkartan.se har en snygg "vad händer nu"-ruta. Får det att kännas som
> att nåt **händer nu**.
>
> ![[CleanShot 2024-04-10 at 19.43.45@2x.png|300]]
>
> Även DN Direkt känns "vad händer nu".
>
> ![[CleanShot 2024-04-22 at 08.43.15@2x.png|400]]

Live-känsla: en kompakt "feed-ruta" på startsidan som visar de senaste
3–5 händelserna med tidsstämpel ("4 min sedan", "12 min sedan") och en
diskret pulserande indikator. Får sajten att kännas levande, inte
arkivisk.

## Bakgrund

- Brottsplatskartan har idag en lista av senaste händelser, men utan
  visuell signal om "live"-status. Bilden i inboxen jämför med
  Krimkartan.se och DN Direkt — båda har en tunn rad/banner överst
  med rullande senaste-uppdateringar.
- Bildreferenser (i Obsidian-vault):
    - `attachments/CleanShot 2024-04-10 at 19.43.45@2x.png` (Krimkartan)
    - `attachments/CleanShot 2024-04-22 at 08.43.15@2x.png` (DN Direkt)
- Tekniskt enkelt: vi cachar redan `crime_events` listor i Spatie
  Response Cache; ny komponent kan återanvända befintlig query.

## Förslag

1. **Komponent:** Blade-component `<x-vad-hander-nu :events="$liveEvents" />`
   — kompakt feed med:
    - 3–5 senaste events (filtrerat på `is_public=true`, ordnat på
      `pubdate desc`)
    - Tidsstämpel som "X min sedan" via `getPubDateFormattedForHumans()`
    - Plats (kommun + län)
    - Brottstyp-ikon
    - Diskret pulserande dot (CSS `@keyframes`, ingen JS)
2. **Placering:** överst på `/` (ovanför karta eller bredvid). Kanske
   även på `/lan/{lan}` med län-filter.
3. **Auto-refresh:** initialt **ingen JS-polling** — sidan rerendrar
   vid nästa cache-miss (≤ 5 min). Senare: turbo-frame med 60s polling
   om data visar att returning users finns.
4. **Schema.org:** undvik markup på live-feed-rutan — den ändrar sig,
   schema-konsistens lider. Renderas som vanlig HTML.
5. **CWV:** komponenten är < 5 KB HTML och har ingen ny JS — ingen
   LCP-risk. Reservera höjd med `min-height` så den inte CLS:ar in.

## Risker

- **Cache-vs-live-spänning:** 5 min response cache betyder att
  "live"-rutan kan vara 5 min stale. Acceptabelt — användarens
  uppfattning är "färskt", inte "millisekund-färskt".
- **Designrisk:** känns falskt om händelserna är 30 min gamla.
  Visa bara events < 60 min gamla; om < 1 finns nyligen, dölj rutan
  helt eller visa "Senaste händelse: 2h sedan" mer dämpat.
- **Mobile real estate:** vid 320px bredd kan rutan tävla med
  hjältan/karta — designa mobile-first.

## Confidence

**Hög.** Liten, väl avgränsad komponent. Värdet är "feels
right" — svår att A/B-testa men låg risk.

## Beroenden

- Inga blockerare. Bygger på befintliga `CrimeEvent`-queries.

## Nästa steg

1. Hämta de två CleanShot-bilderna ur Obsidian-vaulten och placera
   som referens i en local design-doc (utanför git, dela inte
   externa skärmavbilder).
2. Skissa komponent (Tailwind/SCSS) — 1–2 varianter.
3. Bygg + deploy bakom feature flag (?show_live=1) för pilot.
4. Mät dwell time + bounce rate 14d post-launch (GA4) på `/`.
5. Om positivt: bredd-rollout, ev. på `/lan/{lan}` också.
