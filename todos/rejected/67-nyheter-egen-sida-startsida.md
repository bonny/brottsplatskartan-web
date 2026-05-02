**Status:** avfärdad 2026-05-02 — två reviews (SEO + användarvärde) pekar åt samma håll: brand-mismatch, ingen unik vinkel, ingen återbesöks-driver. Pivotera energin till event ↔ artikel-kopplingen istället.
**Senast uppdaterad:** 2026-05-02

# Todo #67 — Nyheter: egen flik/sida och/eller på startsidan

## Sammanfattning

Vi har RSS-grund (#63) och per-plats-aggregering (#64) live, men nyheterna
är idag bara synliga inbäddat på ort-/platsidor. Frågan: borde nyheter
också ha en egen samlingsvy (t.ex. `/nyheter`) och/eller en synlig modul
på startsidan?

**Beslut 2026-05-02: avfärdas.** Två oberoende reviews (SEO/teknisk +
användarperspektiv) pekade åt samma håll. Den unika vinkeln är inte en
nyhetsflik — den är **event ↔ artikel-kopplingen** som #64 redan börjat
bygga. Investera där istället.

## Bakgrund

- #63 deployade RSS-pipelinen 2026-05-01 (29 källor, 90d retention).
- #64 fas 1 deployad samma dag — classify-command + UI på city/plats. 1013
  artiklar, 195 blåljus-träffar, 132 place-news-kopplingar.
- I dag exponeras nyheter bara via plats-/ort-vyer.

## Reviews 2026-05-02

### SEO/teknisk review

- Scope-axlarna missar RSS/Atom-feed, per-län-flöden, och "ingen ny sida —
  bara flöden + #59-modul" som fjärde alternativ.
- SEO-mitigationen var fel formulerad: `canonical till extern domän`
  ignoreras av Google. Rätt mönster: `noindex, follow`.
- Beroendeordningen fel: bör vänta på #64:s precision-stickprov
  2026-05-15 (gate <85 % → bygg ej) och #46:s meny-beslut. #59 är **ingen
  blockerare** — startsidemodul-spåret är i praktiken #59 utvidgad till
  nyheter, slå ihop dem istället för att duplicera.
- Saknat beslutsunderlag: GA4-CTR på #64:s `place-news`-länkar, GSC-impressions
  på "nyheter [ort]" / "blåljus [ort]", tekniskt minimalt scope (1 route +
  1 controller, ingen ny tabell).

### Användarperspektiv-review

- **Vill användare ha det? Nej.** Typisk besökare kommer från Google på
  `"[brott] [ort]"` — vill veta vad som hänt _där_, inte scrolla riks-feed.
- **Driver inte återbesök.** Krimkartans värde är karta + push på
  närområde, inte feeden. Aftonbladet Blåljus funkar p.g.a. redaktionellt
  urval. Vi har varken eller.
- **Lokal vinner stort** — modulen vi redan har (`Senaste nyheter i {ort}`
  via #64) är där värdet ligger. Riks-flöde är "någon annans produkt".
- **Brand-risk:** är vi "kartan över polishändelser" eller en nyhetssajt?
  En `/nyheter`-flik förvirrar både Google och användare.
- **Den unika vinkeln vi missar:** ingen annan har kopplingen _polishändelse
  på karta_ ↔ _artikel som rapporterar om eventet_. Det är moaten.
  Investera _där_: visa på event-sidan "Det här rapporteras om i: Corren,
  SVT Öst".

## Beslut

**Avfärdas till förmån för:**

1. Vänta in #64:s precision-stickprov 2026-05-15 + 30d-CTR/dwell-data
   2026-05-31. Utan kvalitetsdata är vidare bygge gissning.
2. Pivotera energin till **event ↔ artikel-kopplingen på event-sidan**
   ("Det här rapporteras om i: …"). Bygger på #64:s pipeline, har unik
   vinkel ingen annan har, kräver inga nya routes/sidor och har ingen
   brand-konflikt. Bryts ut till egen todo om/när #64-data motiverar det.

## Confidence

hög — båda reviews oberoende landade på samma slutsats. Avfärdandet är
inte "kanske senare" utan "fel produktriktning"; senare-spåret (event ↔
artikel-kopplingen) är ett separat bygge som inte ärver detta scope.
