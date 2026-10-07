**Status:** klar 2026-10-07 — nyhetslistan dedupar på `NewsArticle::storyKey`; MatchEventNews kopierar beslutet för redan bedömda storys i stället för nytt Haiku-anrop (35 % av anropen). Verifierat på prod på event 509606 (`1889bef7`).
**Senast uppdaterad:** 2026-10-07
**Källa:** Inbox Brottsplatskartan (2026-10-04)

# Todo #103 — Text TV-dubbletter på händelsesidans nyhetslista

## Sammanfattning

Hur kan vi lösa detta att länkar till tectv.nu kommer flera gånger
![[Screenshot_20260913-072135.png]]

## Bakgrund

Skärmbilden (2026-09-13, mobil, en Stockholmshändelse) visar samma post,
"SVT Text TV — Dödsolycka i centrala Stockholm", minst tre gånger i rad i
nyhetslistan på en händelsesida.

#82 införde `NewsArticle::storyKey($source, $title)` och dedup på
visningsvägen (`4e49b7d`). Antingen går den här listan förbi den dedupen,
eller så skiljer sig titlarna på något sätt som normaliseringen inte fångar
(t.ex. "INRIKES PUBLICERAD 12 SEPTEMBER" i brödtexten, olika sidnummer).

## Förslag

1. Hitta händelsen och vilka `news_articles`-rader som är kopplade.
2. Kör `storyKey` på dem: får de samma nyckel? Om inte, varför?
3. Leta upp vilken partial/kodväg som renderar listan och om den dedupar.
4. Fixa i `storyKey` eller på visningsvägen (ingen egen normalisering).

## Risker

## Confidence

medel — dedup-mekanismen finns redan, troligen en kodväg som missats.
