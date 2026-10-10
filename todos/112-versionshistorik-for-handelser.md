**Status:** aktiv — ny 2026-10-10, ingen kod skriven
**Senast uppdaterad:** 2026-10-10
**Källa:** Pär 2026-10-10, i samband med #109.6 (ändrade händelser läses in)

# Todo #112 — Versionshistorik för händelser som Polisen ändrar

## Sammanfattning

Sedan #109.6 (2026-10-10) läser importen in när Polisen ändrar titel eller
sammanfattning i efterhand, och sedan #109.2 när detaljsidan ändras. Den gamla
versionen skrivs över. Förslag: **spara varje version internt**, och **visa
användaren när och hur händelsen uppdaterats** — "Uppdaterad 17:42" och
ändrad rubricering/plats — men **inte gamla fritexter**.

## Bakgrund

Mätt på prod 2026-10-10 (`tmp-111/jamfor-api.php`): 14 av 500 händelser i
API:t skilde sig från det sparade. Typer av ändringar:

| Typ                   | Exempel                                                   | Värde att visa              |
| --------------------- | --------------------------------------------------------- | --------------------------- |
| Ändrad rubricering    | 511009 "Knivlagen" → "Mord/dråp, försök"                  | Högt — nyhetsvärt           |
| Ändrad plats          | 510995 "Linköping" → "Östergötlands län"                  | Medel                       |
| Avslutad (försvunnen) | 511089, 511023 "söker efter en man" → "Man anträffad"     | Bara nya texten — se Risker |
| Utökad text           | 511013 "En buss välter" → "En buss med flera passagerare" | Lågt                        |
| Stavfel               | 511110 "misstäkta" → "misstänkta"                         | Inget                       |

Polisen skriver redan ut uppdateringar i brödtexten när det spelar roll
("Uppdatering 16:52 …", 511013), så en egen textdiff dubblerar ofta.

`CrimeEvent::getStructuredData()` sätter redan `dateModified`, men från
`updated_at`. Den ändras vid varje sparning (geokodning, AI-titel,
publicitetskontroll), inte bara när Polisen ändrat något — signalen är brus.

## Förslag

1. **Tabell `crime_event_versions`** (egen tabell, inte kolumner på
   `crime_events`): `crime_event_id`, `title`, `description`,
   `parsed_content`, `polisen_type`, `parsed_title_location`, `andrad_at`,
   `kalla` (`api`/`sida`). Skrivs i `FeedController::uppdateraFranApi()` och
   `tolkaOmEfterAndring()` innan den gamla versionen skrivs över. Egen tabell
   för att inte svälla `crime_events`, som cachas som hela modeller i Redis
   (samma skäl som `polisen_raw` slutade sparas, #111).
2. **Kolumn `polisen_andrad_at` på `crime_events`** — när Polisen senast
   ändrade händelsen. Används för "Uppdaterad HH:MM" och för `dateModified`
   i stället för `updated_at`. Det blir en ALTER på `crime_events` (38 s
   skrivlås på prod, se prod-ops-skillen): **ta bort den tomma
   `polisen_raw` i samma ALTER.**
3. **Visa på händelsesidan:** "Uppdaterad 17:42" när `polisen_andrad_at`
   finns, och en rad när rubricering eller plats ändrats ("Polisen har
   ändrat rubriceringen från Knivlagen till Mord/dråp, försök"). Inga gamla
   fritexter.
4. **Mät först om "uppdaterad" är värt att visa:** jämför i GSC/GA4 trafik
   på händelser som ändrats mot oförändrade (de ändrade finns i loggen
   `Omgeokodad efter ändring hos Polisen` och, efter punkt 1, i tabellen).

## "Uppdaterad!" i titeln? (Pär, 2026-10-10)

Frågan: lockar det fler besökare/klick från Google att sätta "Uppdaterad!"
i `<title>` på händelser som Polisen ändrat? Bedömning: **nej, inte som
första steg.**

- Google skriver ofta om titlar med lockrop/standardtext som inte beskriver
  innehållet.
- Vår egen mätning (#36) visade att titeländringar (AI-titlar) gav _lägre_
  CTR (−8,5 %) — titlar har inte varit en klickhävstång här.
- Bara ~3 % av händelserna ändras, och de flesta får sina klick inom ett par
  dygn.

Gör i stället punkt 2–3 (synligt "Uppdaterad 17:42" på sidan och i listor,
ärligt `dateModified`, rad om ändrad rubricering). Vill vi ändå prova
titeltillägget: bara när rubricering eller plats ändrats (inte stavfel), och
mät CTR i GSC för ändrade händelser mot oförändrade under 30 dagar.

## Risker

- **Integritet:** när en försvunnen person hittas tar Polisen bort
  signalementet med flit. Gamla texter får därför aldrig visas publikt, och
  versionstabellen ska inte exponeras i API:t. Överväg att inte spara
  `parsed_content` för typen "Försvunnen person", eller rensa den när
  händelsen avslutas.
- **Rättelser:** en gammal version med fel plats eller uppgift vilseleder om
  den visas — ytterligare skäl att bara visa ändrad rubricering/plats.
- **`dateModified`-byte:** sidor som idag får ett färskt `dateModified` vid
  varje sparning får ett äldre värde. Bör vara ärligare mot Google, men följ
  upp i GSC.

## Confidence

medel — ändringarna är mätta och verkliga (~3 % av händelserna), men om
"uppdaterad"-signalen ger trafik är omätt (punkt 4).
