# Polisens händelse-API

Brottsplatskartan importerar polishändelser från Polisens officiella JSON-API.
Denna sida beskriver vad datan innehåller, vilka regler som gäller och hur vi
använder den.

- **Endpoint:** <https://polisen.se/api/events> — returnerar 500 senaste händelser.
- **Officiell dokumentation:** <https://polisen.se/om-polisen/om-webbplatsen/oppna-data/api-over-polisens-handelser/>
- **Användarvillkor:** <https://polisen.se/om-polisen/om-webbplatsen/oppna-data/regler-for-oppna-data/>

## Datafält per händelse

```json
{
    "id": 633436,
    "datetime": "2026-04-29 14:28:38 +02:00",
    "name": "29 april 13.37, Sexualbrott, Stockholm",
    "summary": "Kontroll av misstänkt blottare.",
    "url": "/aktuellt/handelser/2026/april/29/29-april-13.37-sexualbrott-stockholm/",
    "type": "Sexualbrott",
    "location": {
        "name": "Stockholms län",
        "gps": "59.602496,18.138438"
    }
}
```

Fält och hur vi använder dem:

| Fält            | Mening                                       | Lagras som                                  |
| --------------- | -------------------------------------------- | ------------------------------------------- |
| `id`            | Stabilt nummer hos Polisen                   | `crime_events.polisen_id` (indexerad, dedup) |
| `datetime`      | Polisens publicerings-/uppdateringstid       | `pubdate` (unix), `pubdate_iso8601`          |
| `name`          | Titel: "DD månad HH.MM, Typ, Plats"          | `title` + `parsed_title` + `parsed_date`     |
| `summary`       | 1-meningssammanfattning                      | `description` (vi hämtar full text separat)  |
| `url`           | Relativ URL till Polisens detaljsida         | `permalink` (prefix `https://polisen.se`)    |
| `type`          | Brottskategori (separat fält)                | parsas idag ur `name`; `type` ej lagrad     |
| `location.name` | Län-namn ("Stockholms län") — alltid län     | `polisen_location_name`                      |
| `location.gps`  | Mittpunkt för **län/kommun**, ej event-precis | `polisen_gps_lat`, `polisen_gps_lng`         |

### Viktiga nyanser

- **`location.gps` är inte event-koordinater** — det är län- eller
  kommun-mittpunkten. Vi använder den som viewport-bias (~50 km bbox)
  i Google Geocoding-anropet för att förbättra träff på tvetydiga
  ortnamn ("Partille"). Den exakta event-koordinaten geokodas separat
  utifrån ortnamnet i titeln.
- **`name` har formatet "DD månad HH.MM, Typ, Plats"** — datum saknar år.
  Vid årsskifte (titel "31 december" publicerad 1 januari) eller
  natt-händelser (titel kl 22:00 publicerad 06:58 morgonen efter)
  hamnar `parsed_date` i framtiden om vi tolkar året naivt.
  `FeedController::parseItem` korrigerar via `subDay`/`subYear` om
  `parsed_date` skulle hamna efter `now()`.
- **`summary` är kortare än vad RSS gav** — för full brödtext skrapar vi
  detaljsidan via `parseItemContentAndUpdateIfChanges` (fyller
  `parsed_teaser` och `parsed_content`).

## Rate-limits (officiella)

| Regel              | Värde       |
| ------------------ | ----------- |
| Min mellan anrop   | 10 sekunder |
| Max per timme      | 60 anrop    |
| Max per dygn       | 1440 anrop  |
| Vid överskridning  | HTTP 429    |

Brottsplatskartans inställning: 75 sekunder cache i Redis (`Cache::put`)
→ max ~48 anrop/h, väl under taket. Lyckade svar cachas; vid HTTP-fel
loggas det och vi försöker igen vid nästa schedule-tick.

Webbskrapning är **förbjuden** enligt Polisens regler — undantag är att
hämta en specifik detaljsida för enskilda events (vilket vi gör för
`parsed_content`).

## Filter-parametrar (används inte idag)

| Parameter      | Exempel                              | Användning                       |
| -------------- | ------------------------------------ | -------------------------------- |
| `DateTime`     | `?DateTime=2026-04`                  | Filtrera på månad/dag/timme      |
| `locationname` | `?locationname=Stockholm;Järfälla`   | Flera platser via semikolon      |
| `type`         | `?type=Misshandel;Rån`               | Flera typer via semikolon        |

Vi använder default-endpointen (500 senaste) eftersom:

- Storleken är trivial (~150–300 KB)
- 500-fönstret fungerar som catch-up när schedulern varit nere
- Filter ger ingen praktisk vinst för full-fångst-användning

Filtrerade endpoints kan vara aktuella vid backfill-körningar (`?DateTime=2025-12`)
eller om vi någon gång parallelliserar import per län.

## Var i koden

| Plats                                                       | Vad                                  |
| ----------------------------------------------------------- | ------------------------------------ |
| `app/Http/Controllers/FeedController.php::updateFeedsFromPolisen` | Hämtning + dedup + `CrimeEvent`-create |
| `FeedController::parseItem`                                  | Titel-parsing + datum-korrigering    |
| `FeedController::geocodeItem`                                | Google Geocoding med viewport-bias   |
| `FeedController::parseItemContentAndUpdateIfChanges`         | Skrapa Polisens detaljsida för full text |
| `app/Console/Commands/FetchEvents.php`                       | `crimeevents:fetch` artisan-kommando |
| `app/Http/Controllers/FeedParserController.php::parseTitle`  | Extrahera datum/titel/plats ur `name` |
