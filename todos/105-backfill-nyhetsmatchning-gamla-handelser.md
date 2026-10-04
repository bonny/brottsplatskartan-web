**Status:** aktiv
**Senast uppdaterad:** 2026-10-04
**Källa:** Inbox Brottsplatskartan (2026-10-04)

# Todo #105 — Backfilla nyhetsmatchning för gamla högtrafikerade händelser

## Sammanfattning

Varför matchas inte media/nyheter till denna? Det finns flera nyheter som handlar om denna ju.
https://brottsplatskartan.se/stockholm/explosion-huddinge-502381

**Status 2026-08-21: delvis.** Matchningen är omgjord (#82) och kör sedan 2026-08-21 var 15:e
minut för färska händelser. Men urvalet tittar bara på de senaste dygnens händelser — den här från
maj har fortfarande noll nyheter. **Öppen fråga: ska gamla högtrafikerade händelser backfillas?**

## Bakgrund

EventNewsMatcher (#82, kostnad i #102) körs bara på färska händelser.
Händelser från före omgjorda matchningen saknar nyheter trots att de har
trafik.

## Förslag

1. Ta fram de N mest besökta händelserna (`crime_views`) utan matchade
   nyheter.
2. Uppskatta kostnaden för en engångskörning med samma cap/dedup som
   produktionskoden (se docs/ai-kostnad.md).
3. Finns nyhetsartiklarna ens kvar i `news_articles` för så gamla händelser?

## Risker

AI-kostnad; gamla artiklar kan vara rensade.

## Confidence

låg — oklart om värdet motiverar kostnaden.
