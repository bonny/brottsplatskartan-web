**Status:** aktiv (skissad + SEO/AdSense-review klar 2026-04-29; väntar på beslut om lagrings-strategi och indexerbarhet innan kod)
**Senast uppdaterad:** 2026-04-29
**Relaterad till:** #40 (Trafikverket STRADA — historisk parallell), #51 (övriga live-källor)

# Todo #50 — Trafikverket Trafikinformation: live-events på kartan

## Sammanfattning

Polisens RSS rapporterar bara ett urval av trafikolyckor (blåljus,
dödsolyckor, större störningar). Trafikverkets **Trafikinformation
API** har **alla** registrerade händelser på statlig väg i realtid —
trafikolyckor, vägarbeten, vägstörningar, avstängningar, vinterväglag,
färjelägen, vilt, kameror.

Direktkomplement till vår existerande Leaflet-karta. Punkt-koordinater
finns i datat (SWEREF99 TM + WGS84). Gratis, kräver bara registrerad
API-nyckel.

## Bakgrund

Brottsplatskartan visar händelser från Polisens RSS. Polisen rapporterar
selektivt — Trafikverket täcker mycket Polisen aldrig nämner:

- Vägarbeten med trafikomledning
- Korta avstängningar (3–30 min)
- Mindre olyckor utan polis-insats
- Vilt på väg
- Vinterväglags-varningar
- Färjeläges-störningar

Källan är **den** primära för svensk trafikincident-data.

## Datakälla

- **Endpoint:** `https://api.trafikinfo.trafikverket.se/v2/data.json`
- **Dokumentation:** https://data.trafikverket.se/
- **Format:** REST/JSON eller XML, query-baserat
- **Licens:** Öppen, kräver gratis API-nyckel (registrering på
  trafikinfo.trafikverket.se)
- **Realtid:** Ja, live (push via WebSocket finns men overkill för oss)
- **Geografi:** Punkt-koordinater per `Situation`/`Deviation`

Relevanta object-typer:

- `Situation` (container) → `Deviation[]` — incidenter, olyckor, vägarbeten.
  En Situation kan innehålla flera Deviations med var sin koordinat.
- `RoadCondition` — vinterväglag
- `WeatherStation` — väglagsdata realtid
- `Camera` — trafikkameror (sekundärt)

## Öppna designval (måste avgöras innan migration skrivs)

### 1. Lagrings-mönster

Tre alternativ — välj **innan** schemat hamnar i en migration:

- **(A) Egen tabell `trafikverket_deviations`** — enklast nu, men #51
  introducerar minst 3 källor till (SMHI, räddningstjänst, krisinfo).
  4–6 tabeller med 80% överlappande kolumner skalar dåligt; frontend
  måste fetcha N endpoints och slå ihop.
- **(B) Ny gemensam `events`-tabell med `source ENUM`-kolumn** —
  polymorf rad, gemensamma kolumner + `payload JSON` för källspecifikt.
  `crime_events` lever vidare som-är (för stort att migrera nu); nya
  källor går i `events`. På sikt kan `crime_events` migreras in.
  **Rekommenderat alternativ.**
- **(C) Utöka `crime_events` med `source` + `external_id`** — snabbast,
  men `crime_events` har AI-rewrite-fält (`title_alt_1`,
  `description_alt_1`), `is_public`-scope, `parsed_*`-pipeline som är
  död vikt för Trafikverket-rader. Tabellnamnet ljuger sen.

### 2. Dedup mot Polisens RSS

"Trafikolycka" finns i båda källorna. Behöver lösning i kod, inte bara UI:

- När Trafikverket-rad och Polis-rad har `message_type=Olycka` inom
  ~500 m och ~30 min — antingen länka dem (`related_event_id`) eller
  dölj Trafikverket-versionen.
- Polisens rad har AI-rewrite + permalänk → den prioriteras vid krock.

### 3. Spara historik?

Om kartan bara visar aktiva räcker `WHERE end_time IS NULL OR end_time > NOW()`
— men då tappas "antal vägarbeten Q1 2026". Beslut innan bygget,
annars läcker scope.

## Förslag

### Schema (utgår från alternativ B — `events`-tabell)

Vid alternativ A: byt tabellnamn till `trafikverket_deviations` och
ta bort `source`-kolumnen. Övriga fält samma.

```sql
CREATE TABLE events (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source VARCHAR(32) NOT NULL,                -- 'trafikverket', 'smhi', 'krisinfo', framtida 'polisen'
  external_id VARCHAR(64) NOT NULL,           -- t.ex. Trafikverkets Deviation.Id
  parent_external_id VARCHAR(64) NULL,        -- t.ex. Situation.Id (en Situation har flera Deviations)
  message_type VARCHAR(64) NOT NULL,          -- 'Olycka', 'Vägarbete', 'Vilt', 'Avstängning'
  severity_code TINYINT NULL,                 -- Trafikverkets SeverityCode 1–5; lagra rått, mappa vid render
  icon_id VARCHAR(64) NULL,                   -- Trafikverkets IconId
  message TEXT NULL,                          -- nullable: RoadCondition har ofta ingen Message
  location_descriptor TEXT NULL,              -- TEXT istället för VARCHAR(255) — kan bli långt
  road_number VARCHAR(32) NULL,               -- "E4", "Rv40"
  county_no SMALLINT NULL,                    -- Trafikverkets numeriska länskod (1–25)
  administrative_area_level_1 VARCHAR(64) NULL, -- "Stockholms län" — för join mot resten av appen
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  start_time TIMESTAMP NOT NULL,
  end_time TIMESTAMP NULL,
  created_time TIMESTAMP NOT NULL,
  modified_time TIMESTAMP NOT NULL,
  related_event_id BIGINT UNSIGNED NULL,      -- dedup: pekar på matchande crime_event vid krock
  source_url VARCHAR(500) NULL,
  payload JSON NULL,                          -- källspecifika fält som inte hör hemma i kolumner
  imported_at TIMESTAMP NOT NULL,
  UNIQUE KEY idx_source_external (source, external_id),
  KEY idx_geo (lat, lng),                     -- B-tree (samma som crime_events idag); SPATIAL POINT övervägt men inte nödvändigt
  KEY idx_time (start_time, end_time),
  KEY idx_source_active (source, end_time),   -- snabb "aktiva från Trafikverket"-query
  KEY idx_county_time (county_no, start_time)
);
```

**Viktigt:** **en rad per Deviation, inte per Situation.** Trafikverkets
Situation är en container med flera Deviations som var och en har egen
koordinat och tid.

### Import-jobb

```bash
docker compose exec app php artisan trafikverket:fetch
```

Schemaläggs i `app/Console/Kernel.php` **var 2:a minut** (tightare än
crimeevents:fetch — Trafikverket är "live"-data där 5+ min stale syns).
Idempotent på `(source, external_id)` — uppdatera vid `modified_time`-bump.

### Pruning

```sql
DELETE FROM events
WHERE source = 'trafikverket'
  AND end_time IS NOT NULL
  AND end_time < NOW() - INTERVAL 30 DAY;
```

Aktiva situations (`end_time IS NULL`) sparas oavsett ålder.

### Modell + Helper

```php
\App\Models\Event                                 // generisk; scope:ar på source
\App\Helpers\TrafikInfo::activeForBounds($swLat, $swLng, $neLat, $neLng)
\App\Helpers\TrafikInfo::activeForCounty($lanCode)
```

### UI

- **Egen Leaflet-layer** med skild ikon (vägikon i orange/röd) — inte
  blandat med polishändelse-markers.
- **Layer toggle** i kart-kontroller: "Trafikhändelser (Trafikverket)".
- **Default OFF** initialt — 5–15k aktiva Trafikverket-situations gör
  kartan stökigare än befintliga användare är vana vid. Toggle synlig
  och tydlig. Ändra default till PÅ senare om feedback är positiv.
- **Egen sida `/trafik`** med bara Trafikverket-data (sekundärt mål).

### API-utbyggnad

`/api/eventsMap` får `?source=polisen|trafikverket|all` (default `polisen`
för bakåtkompat). **Shape unifieras** — `source`-fält läggs till även
för Polisens rader så frontend kan switch:a på styling:

```json
{
    "source": "trafikverket",
    "id": 12345,
    "lat": 59.3293,
    "lng": 18.0686,
    "time": "14:23",
    "message_type": "Olycka",
    "headline": "Trafikolycka E4 söderut",
    "permalink": null,
    "severity": "high"
}
```

Cache-TTL: 1 minut (mot 5 min för polisen) — Trafikverket-datat är
färskare och försvinner snabbare.

## Exempel-data

Representativt utdrag från Trafikverkets `Situation`-objekt (schema
v1.5). Verifieras mot riktig respons när API-nyckeln är registrerad —
fält-namn dokumenterade men enum-värden kan ha ändrats mellan versioner.

### Rå API-respons

Query: senaste `Situation` med `MessageType=Olycka` på E4 i Stockholms
län, plus två andra typer för kontrast.

```json
{
    "RESPONSE": {
        "RESULT": [
            {
                "Situation": [
                    {
                        "Id": "SE_STA_TRISSID_1_15783923",
                        "CreationTime": "2026-04-29T13:42:11.000+02:00",
                        "VersionTime": "2026-04-29T13:58:04.000+02:00",
                        "PublicationTime": "2026-04-29T13:42:30.000+02:00",
                        "ModifiedTime": "2026-04-29T13:58:04.000+02:00",
                        "Deviation": [
                            {
                                "Id": "SE_STA_TRISSID_1_15783923_1",
                                "IconId": "accident",
                                "MessageType": "Olycka",
                                "MessageCode": "Accident",
                                "Header": "Trafikolycka E4 i höjd med Upplands Väsby",
                                "Message": "Singelolycka i södergående riktning. Ett körfält avstängt. Räddningstjänst på plats. Räkna med köbildning.",
                                "SeverityCode": 3,
                                "SeverityText": "Stora konsekvenser",
                                "LocationDescriptor": "E4 mellan Upplands Väsby och Rotebro, södergående",
                                "RoadNumber": "E4",
                                "RoadNumberNumeric": 4,
                                "CountyNo": [1],
                                "AffectedDirection": "Söderut",
                                "StartTime": "2026-04-29T13:38:00.000+02:00",
                                "EndTime": "2026-04-29T15:30:00.000+02:00",
                                "Geometry": {
                                    "Point": {
                                        "WGS84": "POINT (17.9123456 59.5234567)",
                                        "SWEREF99TM": "POINT (665432 6601234)"
                                    }
                                },
                                "ManagedCause": false,
                                "TrafficRestrictionType": "Körfält avstängt",
                                "TemporaryLimit": [
                                    { "Value": "70", "Unit": "km/h" }
                                ],
                                "WebLink": "https://trafikinfo.trafikverket.se/sv/trafikinformation/vag/15783923"
                            }
                        ]
                    },
                    {
                        "Id": "SE_STA_TRISSID_1_15784101",
                        "CreationTime": "2026-04-29T13:55:02.000+02:00",
                        "ModifiedTime": "2026-04-29T13:55:02.000+02:00",
                        "Deviation": [
                            {
                                "Id": "SE_STA_TRISSID_1_15784101_1",
                                "IconId": "roadwork",
                                "MessageType": "Vägarbete",
                                "Header": "Vägarbete väg 73",
                                "Message": "Beläggningsarbete pågår. Ett körfält avstängt växelvis. Sänkt hastighet 50 km/h.",
                                "SeverityCode": 1,
                                "SeverityText": "Mindre konsekvenser",
                                "LocationDescriptor": "Väg 73 vid Handen",
                                "RoadNumber": "73",
                                "CountyNo": [1],
                                "StartTime": "2026-04-29T07:00:00.000+02:00",
                                "EndTime": "2026-05-15T18:00:00.000+02:00",
                                "Geometry": {
                                    "Point": {
                                        "WGS84": "POINT (18.1456789 59.1678901)"
                                    }
                                }
                            }
                        ]
                    },
                    {
                        "Id": "SE_STA_TRISSID_1_15783888",
                        "Deviation": [
                            {
                                "Id": "SE_STA_TRISSID_1_15783888_1",
                                "IconId": "moose",
                                "MessageType": "Vilt",
                                "Header": "Älg på vägen",
                                "Message": "Älg observerad på och i anslutning till vägbanan. Kör försiktigt.",
                                "SeverityCode": 2,
                                "LocationDescriptor": "Väg 222 norr om Boo",
                                "RoadNumber": "222",
                                "CountyNo": [1],
                                "StartTime": "2026-04-29T13:20:00.000+02:00",
                                "EndTime": null,
                                "Geometry": {
                                    "Point": {
                                        "WGS84": "POINT (18.2987654 59.3456789)"
                                    }
                                }
                            }
                        ]
                    }
                ]
            }
        ]
    }
}
```

### Hur det normaliseras till `events`-tabellen

3 rader (en per Deviation), efter parsning:

| source       | external_id      | parent_external_id | message_type | severity_code | road_number | county_no | adm_1          | lat     | lng     | start_time | end_time         |
| ------------ | ---------------- | ------------------ | ------------ | ------------- | ----------- | --------- | -------------- | ------- | ------- | ---------- | ---------------- |
| trafikverket | `..._15783923_1` | `..._15783923`     | Olycka       | 3             | E4          | 1         | Stockholms län | 59.5235 | 17.9123 | 13:38      | 15:30            |
| trafikverket | `..._15784101_1` | `..._15784101`     | Vägarbete    | 1             | 73          | 1         | Stockholms län | 59.1679 | 18.1457 | 07:00      | 2026-05-15 18:00 |
| trafikverket | `..._15783888_1` | `..._15783888`     | Vilt         | 2             | 222         | 1         | Stockholms län | 59.3457 | 18.2988 | 13:20      | NULL             |

`message`, `location_descriptor`, `icon_id`, `source_url` lagras också;
`payload JSON` får råa fält som `AffectedDirection`,
`TrafficRestrictionType`, `TemporaryLimit` (sällan-användna men värda
att behålla utan eget kolumn-bråk).

### Vad `/api/eventsMap?source=all` returnerar

Unifierad shape, frontend switch:ar på `source` för markers/styling:

```json
{
    "data": [
        {
            "source": "polisen",
            "id": 8821334,
            "lat": 59.3293,
            "lng": 18.0686,
            "time": "13:45",
            "message_type": "Trafikolycka, singel",
            "headline": "Singelolycka på E4 — föraren oskadd",
            "permalink": "/handelse/8821334/...",
            "severity": null
        },
        {
            "source": "trafikverket",
            "id": 4471,
            "lat": 59.5235,
            "lng": 17.9123,
            "time": "13:38",
            "message_type": "Olycka",
            "headline": "Trafikolycka E4 i höjd med Upplands Väsby",
            "permalink": null,
            "severity": "high",
            "road_number": "E4",
            "ends_at": "2026-04-29T15:30:00+02:00"
        },
        {
            "source": "trafikverket",
            "id": 4472,
            "lat": 59.3457,
            "lng": 18.2988,
            "time": "13:20",
            "message_type": "Vilt",
            "headline": "Älg på vägen — väg 222",
            "permalink": null,
            "severity": "medium",
            "road_number": "222",
            "ends_at": null
        }
    ]
}
```

### Dedup-exempel

Polisens rad (`Singelolycka på E4`, 13:45, 59.33 / 18.07) och Trafikverkets
rad (`Olycka E4 Upplands Väsby`, 13:38, 59.52 / 17.91) är **~25 km isär**
→ **inte** samma incident. Båda visas.

Hade de varit < 500 m och < 30 min isär: dedup-jobbet sätter
`events.related_event_id = 8821334` på Trafikverket-raden, och frontend
visar bara Polisens version (eventuellt med "även rapporterat av
Trafikverket"-badge).

## Risker

- **API-nyckel-rotation.** Trafikverket kan kräva förnyelse — rate-limits
  okända innan registrering. **Registrera nyckel innan schemat låses**
  så fält-nomenklaturen verifieras mot riktig respons.
- **Volym.** Live-feed har 5–15k aktiva situations samtidigt. Skriv-
  belastningen är försumbar med idempotent upsert. Pruning enligt ovan.
- **Begreppskrock med Polisens RSS** — se "Öppna designval" punkt 2.
- **Statlig väg-bias.** Kommunala vägar täcks dåligt — innerstads-
  händelser kommer fortfarande mest från Polisen.

## SEO- och AdSense-review (2026-04-29)

### SEO — risker

1. **Thin / scraped content mot Trafikverkets egen sajt.** Trafikverket
   rankar redan på "trafikolycka E4 idag" via `trafikinfo.trafikverket.se`.
   Att mirrora `Header` + `Message` verbatim utan tillagt värde =
   Helpful Content-flagga. Måste kontextualiseras (länsaggregat,
   kombinera med polishändelser, lokal text) — inte ren API-mirror.
2. **Live-events i sitemap → soft 404 / index churn.** Trafikverket-
   händelser försvinner när `end_time` passerar. Schemat har ingen
   `permalink` på Trafikverket-rader (bra), men implementation måste
   _bekräfta_: inga stable URLs per incident, inget i `sitemap.xml`,
   ingen `<link rel="canonical">` som senare 410:ar. Annars bygger vi
   tusentals "killed pages"/månad — exakt vad #29 städar bort.
3. **/trafik och /lan/{lan}/trafik blir tunna aggregat.** Marker-listor
   utan editor-text ↔ samma "informationless URL"-problem. Behöver
   redaktionell text per län, statistikrad, lista över större vägar,
   gärna tids-aggregat när #40 STRADA finns.
4. **AI-omskrivning av `Header` (analogt med #10) — pausa initialt.**
   Trafikverkets data är öppen men derivat-villkor varierar; svenska
   myndigheter har historiskt varit skeptiska till AI-omskriven version
   av officiell info. Kör raw + lokal kontext först, mät, ta beslut sen.

### SEO — konkreta rekommendationer

- **Schema.org:** `SpecialAnnouncement` per incident om detail-vy
  byggs. `NewsArticle` är fel (det här är inte journalistik).
- **Attribution:** "Källa: Trafikverket" + länk till `WebLink` på
  varje incident-detail. E-E-A-T + respekt för Trafikverkets villkor.
- **/trafik:** tillåt indexering MEN cache:a hårt (response cache 5–10
  min) så Googlebot inte triggar full DB-fetch. Min 300 ord redaktionell
  text utöver datan.
- **/lan/{lan}/trafik:** **noindex initialt** tills varje sida har
  unikt innehåll — riskerar annars 21 nästan-identiska sidor (samma
  fälla #29 jobbar bort).
- **Internlänk:** länk från `/lan/{lan}` → `/lan/{lan}/trafik` ger
  discovery utan att blåsa upp ankarprofilen.
- **CWV på `/karta` med Trafikverket-layer på:** 5–15k markers = jank-
  risk på mobil. `Default OFF` är rätt val. När toggle slås på, kräv
  markercluster för att hålla LCP < 2.5s.

### AdSense — risker

1. **Limited original content-flagga.** AdSense har skärpt rejection
   mot dataaggregator-sidor utan tillagt värde. /trafik utan editor-
   text = trolig flagga vid nästa policy-review. Samma åtgärd som SEO:
   kontext + statistik + ortsbeskrivningar.
2. **Sensitivt innehåll → "ad limited".** Dödsolyckor och allvarliga
   skadeolyckor triggar AdSense's content classifier. På incident-vy
   där `MessageType=Olycka` + `SeverityCode >= 4` (eller `Header`
   innehåller "död" / "avliden" / "dödad"): blocka ad-slots eller
   sätt content-flagga så Auto Ads avstår. Bättre att förlora 0.5 %
   impressions än få domänen flaggad.
3. **CLS från async marker-load.** Trafikverket-layer laddar markers
   asynkront → reflow → CLS-poäng sjunker → AdSense-CPM dippar.
   Reservera höjd på kart-container med `min-height` + reservera
   ad-slots med fasta dimensioner.
4. **Overlay-policy.** AdSense förbjuder ads ovanpå interaktiv karta.
   Nuvarande layout (sidopanel + karta) är OK. Bekräfta att Auto Ads
   _inte_ aktiverar vignette/overlay på `/karta` eller `/trafik`.
5. **Bounce-rate-risk.** Live-trafikdata är "kolla snabbt och stäng" →
   bounce-rate stiger → AdSense lägre CPM, GSC "low quality". Mitigera
   med internlänkar från trafikincident → relaterad polishändelse (när
   dedup-jobbet ger en match) + "senaste 24h-statistik"-block.

### AdSense — konkreta rekommendationer

- Ad-block-flagga på incident-vyer med `SeverityCode >= 4` eller
  "död"/"avliden" i `Header`.
- Inga overlay/vignette-ads på `/karta` eller `/trafik`.
- CLS-skydd: `min-height` på kart-container + reserverade ad-slots.
- Mät RPM/CPM 30d post-launch på nya sidor mot domänsnitt. Om < 50 %
  → tunn content, åtgärda.
- Inga ads på `/api/*` (gäller redan, bekräfta för nya endpoints).

### Review-confidence

- **SEO:** medel-hög. Risk låg om "ingen indexering på enskilda
  incidenter, hård kontext på aggregat". Hög upside för niche-queries
  ("vilt på vägen Värmland") som är obesatta.
- **AdSense:** medel. Står och faller med redaktionellt innehåll på
  aggregat. Tunn implementation = medel-låg risk att AdSense-betyg
  tappar. Med editor-text + sensitiv content-flagga = ingen risk.

## Confidence

**Hög.** Källan är välbeskriven, formatet är dokumenterat, koordinater
finns. Enda osäkerheter:

- Rate-limits (kräver registrering för att se)
- Lagrings-mönster (A/B/C) — påverkar omfattning av merge-arbete

Implementation 1–2 dagar inkl. UI vid alternativ A; 3–4 dagar vid
alternativ B (ny tabell + adapter-pattern).

## Beroenden mot andra todos

- Inga blockerare. Kan startas direkt efter API-nyckel registrerats
  och lagrings-mönster valts.
- **Synergi med #51** — vid alternativ B blir `events`-tabellen
  återanvändbar för SMHI/räddningstjänst/krisinfo. Vid alternativ A
  duplicerar man arbetet per källa.

## Nästa steg

1. **Beslut om lagrings-mönster (A/B/C).** Diskutera innan kod.
2. **Beslut om dedup-strategi** mot Polisens RSS.
3. **Beslut om historik** — spara > 30 dagar eller bara aktiva?
4. **Beslut om indexerbarhet** (från SEO/AdSense-review): ska
   enskilda Trafikverket-incidenter ha egen URL-vy? Om ja → noindex
    - ad-block-flagga + ej i sitemap. Om nej → enklare, mindre risk.
5. **Beslut om redaktionellt innehåll på `/trafik` + `/lan/{lan}/trafik`
   före launch.** Skriv min 5–10 aggregat-sidors text _innan_ deploy
   (inte efter). Tunna sidor som indexeras får svår-reverserbar låg
   quality-poäng och drar ner AdSense-betyg.
6. Registrera API-nyckel på trafikinfo.trafikverket.se → spara i
   `.env` som `TRAFIKVERKET_API_KEY`.
7. Skissa query mot `Situation`/`Deviation`-objekten — verifiera fält
   mot riktig respons.
8. Skapa migration + model + import-command.
9. Bygg Leaflet-layer + toggle (default OFF) + ad-block-flagga för
   sensitiva incidenter.
10. Schemalägg fetch var 2:a minut.
11. Soak 1 vecka — success-mått: volym/dygn, dubblett-rate mot Polisens
    RSS (mål: <5 %), render-tid på `/karta` (mål: ingen regression),
    CLS på sidor med kart-layer (mål: < 0.1).

## Inte i scope

- **Vägkamera-bilder** — Trafikverket har bilder men det är annan
  integration (lagring, GDPR-fundering).
- **Historisk olycksdata** — täcks av #40 (STRADA).
- **Vägkvalitet/asfaltsstatus** — inte Brottsplatskartans scope.
- **WebSocket push** — REST-polling var 2:a minut räcker; pushen är
  värd att överväga först om realtidskravet skärps.
