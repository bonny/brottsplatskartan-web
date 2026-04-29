**Status:** avfärdad 2026-04-29 — brand-/UX-/SEO-mismatch; VMA täcker redan det akuta
**Senast uppdaterad:** 2026-04-29

# Todo #49 — Feeda in Krisinformation.se RSS

Importerad från GitHub-issue [#5](https://github.com/bonny/brottsplatskartan-web/issues/5) (gammal — 2018).

## Sammanfattning

Komplettera Polisens RSS-flöden med Krisinformation.se:s nyhets-RSS för
bredare täckning av incidenter, naturhändelser och samhällsstörningar.

Källor:

- Översiktssida: https://www.krisinformation.se/nyheter/nyheter-som-rss
- Direktlänk RSS-flöde: https://www.krisinformation.se/nyheter/?rss=true

## Bakgrund

Brottsplatskartan visar idag bara polisrapporter + VMA-alerts. Krisinformation
har bredare räckvidd (väder, samhällsstörningar, infrastruktur) som
kompletterar utan att överlappa.

Relaterat: VMAAlert-modellen finns redan, så datamodell för
"icke-polishändelser" är delvis etablerad.

## Förslag

1. Undersök Krisinformations RSS — uppdateringsfrekvens, geo-data, kategorier
2. Bedöm overlap med VMA (krisinformation publicerar ofta VMA också)
3. Om värde: ny modell `KrisinfoEvent` eller utökad `VMAAlert`?
4. UI-fråga: ska de blandas med polisrapporter eller visas separat?

## Risker

- Risk för dubbletter med VMA-flödet
- Krisinformation har sällan koordinater → svårt att placera på kartan
- Scope-creep: vad är Brottsplatskartan utan brottsfokus?

## Confidence

låg — produktbeslut snarare än teknisk fråga. Kan vara värt att avfärda
om scope inte stämmer med varumärket.

## Beslut 2026-04-29 — avfärdad

Review ur UX- och SEO-perspektiv landade i avfärdan:

**UX:**

- Brand-mismatch: "Brottsplatskartan" lovar brott, inte väder/elavbrott/sjukdomar.
- Krisinfo-poster har sällan precisa koordinater — passar dåligt på en kart-driven UI.
- Frekvens-mismatch: Polisen publicerar 100+/dag, Krisinfo 0–5/dag. Drunknar i flödet.
- VMA täcker redan det som är akut/lokalt relevant.

**SEO:**

- Topical authority urvattnas — sajten rankar på `crime+geo`, mixad content försvagar signalen.
- Brand-CTR sjunker när snippets handlar om annat än brott.
- Krisinformation.se outranker oss på sin egen content (myndighet, hög auktoritet) → thin content för Google.
- Går emot #29-strategin (precis noindex:at ~22 000 thin pages).

**Övriga nyhetskällor utvärderade och avfärdade i samma review:**
SMHI (väder, ej brott), SOS Alarm (ej publikt), Trafikverket/STRADA (avfärdad #40),
lokaltidningar (upphovsrätt + ingen geo-data), Trafiken.nu (tunn data).

Redan integrerat på rätt sätt: BRÅ-statistik (#38), MSB räddningsstatistik (#39) —
fördjupar crime-/geo-storyn istället för att bredda till andra incident-typer.
