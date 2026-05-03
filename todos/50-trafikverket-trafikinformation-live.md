**Status:** aktiv (skissad + SEO/AdSense-review 2026-04-29 + live-verifiering + arkitektur-review 2026-05-03; **fas 1-plan klar — redo att implementera**)
**Senast uppdaterad:** 2026-05-03
**Relaterad till:** #40 (Trafikverket STRADA — historisk parallell), #51 (övriga live-källor)
**XSD-källa:** `docs/Trafikverket/response_Situation_v1.6.xsd` (auktoritativ).

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

Relevanta object-typer (alla under namespace `Road.TrafficInfo`):

- `Situation` (container) → `Deviation[]` — incidenter, olyckor, vägarbeten.
  En Situation kan innehålla flera Deviations med var sin koordinat.
- `RoadCondition` — vinterväglag
- `WeatherStation` — väglagsdata realtid
- `Camera` — trafikkameror (sekundärt)

### Query-format (verifierat 2026-05-03)

`namespace="Road.TrafficInfo"` är **obligatoriskt** på `<QUERY>` i v2 av API:t.
Utan namespace: `ObjectType 'Situation' does not exists.`

```xml
<REQUEST>
  <LOGIN authenticationkey="..." />
  <QUERY namespace="Road.TrafficInfo" objecttype="Situation" schemaversion="1.6" limit="100">
    <FILTER>
      <AND>
        <EQ name="Deviation.MessageType" value="Olycka" />
        <NE name="Deviation.Suspended" value="true" />
      </AND>
    </FILTER>
  </QUERY>
</REQUEST>
```

`Deviation.Suspended` (nytt i v1.6) anger om händelsen är inaktiv. **Default-respons
innehåller mestadels suspended=true** (~74 % i sample 500). Alla queries måste
filtrera bort `Suspended=true`, annars visas inaktiva markers på kartan.

## Live-verifiering 2026-05-03

Snapshot av aktiva (ej suspended) Situations vid kl ~14:30:

### Hela Sverige

| Antal Deviations | MessageType      |
| ---------------: | ---------------- |
|            1 544 | Trafikmeddelande |
|              803 | Vägarbete        |
|              370 | Hinder           |
|               40 | Färjor           |
|            **3** | **Olycka**       |

1 245 Situations / 2 760 Deviations totalt.

### Per län (top 5)

| Antal | Län                           |
| ----: | ----------------------------- |
|   465 | Norrbottens län (CountyNo=25) |
|   460 | Västra Götalands län (14)     |
|   352 | Västerbottens län (24)        |
|   332 | Stockholms län (1+2¹)         |
|   204 | Jämtlands län (23)            |

¹ `CountyNo=2` är **deprecated** men returneras fortfarande. Mappa till null
eller `1` vid import — annars dubblas Stockholm.

### Severity-fördelning (hela Sverige)

| Antal | SeverityCode | Påverkan    |
| ----: | :----------: | ----------- |
| 1 373 |     null     | (oklassad)  |
|   178 |      5       | Mycket stor |
|   439 |      4       | Stor        |
|   735 |      2       | Liten       |
|    35 |      1       | Liten       |

**~50 % av Deviations saknar SeverityCode-klassning.** SEO-reviewen i denna
todo använder "ad-block vid `SeverityCode >= 4`" — det måste kompletteras
med keyword-match på `Header`/`Message` ("död", "avliden", "dödad", "allvarlig")
för att täcka oklassade allvarliga incidenter.

### Aktivt **startat samma dag** (newsworthy-volym, hela Sverige)

| Antal | Typ              |
| ----: | ---------------- |
|    23 | Trafikmeddelande |
|     8 | Vägarbete        |
|     3 | Olycka           |

### Faktiska olyckor i Sverige just nu (3 st, alla på lokala vägar)

| Tid         | Plats                                             | Severity | Typ                                                          |
| ----------- | ------------------------------------------------- | :------: | ------------------------------------------------------------ |
| 13:01–14:45 | Väg 110 Cirkulationsplats Hyllinge → Bjuv (Skåne) |    5     | Olycka + parallell `Vägen avstängd`-Deviation på samma punkt |
| 13:57–15:00 | Väg 120 Veräng → Mårslycke (Kronoberg)            |    4     | Olycka                                                       |
| 14:08–14:54 | Väg 561 Eldsberga (Halland)                       |    2     | Olycka med tungt fordon                                      |

**Volymbaseline för planning:** ~3–10 olyckor/dygn på riksnivå (jämför med
Polisens RSS som har ~50–100 trafikolyckor/dygn). Trafikverket är **komplement**,
inte ersättning. Vid 90d retention på Olycka: ~270–900 permalink-sidor totalt.

### MessageCode-katalog (Stockholm, sample)

Visar finkorniga subtyper under varje MessageType:

```
Vägarbete:
  Vägarbete                       (180+)
  Beläggningsarbete                  (7)
  Sprängningsarbete                  (3)

Trafikmeddelande:
  Hastighetsbegränsning gäller      (37)
  Körfältsavstängningar             (26)
  Vägen avstängd                    (20)
  Följ omledningsskyltar             (4)
  Fordonshaveri                      (2)
  Brand i fordon                     (1)
  Broöppning                         (1)
  Djur på vägen                      (1)
  Vägskada                           (1)
```

**Newsworthy MessageCodes** (kandidater för specialhantering): `Brand i fordon`,
`Djur på vägen`, `Fordonshaveri`, `Broöppning`, `Vägskada` — i `Trafikmeddelande`-
gruppen. Söks aktivt på "broöppning E4 idag", "djur på vägen 279" etc.

### DATEX-endpointen (`/v2/datex.xml`) — testat, ej användbar

Kunde inte komma åt med någon namespace/objecttype-kombination ur våra XSD-filer
(`MessageContainer`, `PayloadPublication`, `SituationPublication`, `D2LogicalModel`,
m.fl. — alla returnerar `does not exists`). DATEX kräver troligen ett helt annat
anrop-format som inte är dokumenterat publikt. **Spelar ingen roll** — JSON-
endpointen `/v2/data.json` exponerar samma underliggande data i enklare format.

### Intern dedup (Trafikverket-rader sinsemellan)

Olycka #1 ovan (Väg 110) hade **två Deviations på samma `Geometry.WGS84`,
samma `StartTime`, samma `EndTime`**: en `Olycka · Olycka` + en `Trafikmeddelande
· Vägen avstängd`. Samma Situation-Id, olika Deviation-Id.

→ Vid render på kartan: **gruppera Deviations per `parent_external_id` (Situation.Id)**
och visa primär-typen (prioritera Olycka > Vägen avstängd > övrigt). Annars staplas
flera markers på samma punkt och kartan blir oläsbar.

## Designbeslut 2026-05-03

### 1. Lagrings-mönster — **B: ny `events`-tabell**

`crime_events` lever vidare orörd. Ny `events`-tabell tar Trafikverket nu
och #51:s framtida källor (SMHI, räddningstjänst, krisinfo) utan
ytterligare schema-arbete. Polymorf rad: gemensamma kolumner + `payload JSON`
för källspecifikt.

Avfärdade alternativ:

- **(A) Egen tabell per källa** — duplicerat schema-arbete för #51.
- **(C) Utöka `crime_events`** — `crime_events` har AI-rewrite-fält,
  `is_public`-scope och parsed-pipeline som är död vikt för Trafikverket-
  rader. Tabellnamnet ljuger sen.

### 2. Vyer och filtrering

**Plats-sidor (`/stockholm`, `/uppsala`, `/{lan}`, `/{plats}`)** —
mixar **alla källor och alla typer**. Användaren bryr sig om vad som
händer på platsen, inte vem som rapporterat.

**Kategori-sidor:**

| URL                     | Innehåll                                                                                                      |
| ----------------------- | ------------------------------------------------------------------------------------------------------------- |
| `/trafik`               | Trafikverket (alla `MessageType` utom Färjor) + polishändelser där `parsed_title` är trafik-/fordonsrelaterad |
| `/{lan}/trafik`         | samma men filtrerat på län                                                                                    |
| `/trafikolyckor`        | Endast olyckor: Trafikverket `MessageType=Olycka` + polishändelser med trafikolycks-relaterad `parsed_title`  |
| `/{lan}/trafikolyckor`  | samma per län                                                                                                 |
| `/brand`, `/helikopter` | Oförändrade — ren polishändelse-feed                                                                          |

**Polishändelse-`parsed_title` som räknas som trafik** (filter-set):

```
Trafikolycka
Trafikolycka, personskada
Trafikolycka, vilt
Trafikolycka, singel
Trafikolycka, smitning från
Trafikbrott
Rattfylleri
Trafikkontroll  (inkl. plural-stavning "Trafikkontroller")
Trafikhinder
Kontroll person/fordon
```

**Exkluderas från `/trafik`** (även om de involverar fordon):
`Motorfordon, stöld`, `Motorfordon, anträffat stulet`, `Stöld ur fordon`,
`Fylleri/LOB`, `Fylleri`. De hör hemma i stöld-/fyllerivyer.

### 3. Permalinks

| Källa                  | Permalink-mönster                               | Indexeras?                  |
| ---------------------- | ----------------------------------------------- | --------------------------- |
| Polisens trafikolyckor | `/{lan}/trafikolycka-{titel}-{id}` (oförändrat) | Ja                          |
| Trafikverket `Olycka`  | `/trafik/olycka/{tv_id}`                        | Ja, 90d retention sedan 410 |
| Trafikverket övriga    | inga permalinks — bara live-karta + aggregat    | Nej                         |

Två separata permalink-rymder ger riskspridning: en eventuell SEO-fälla i
Trafikverkets temporära-data-rymd påverkar inte polishändelsernas 12-åriga
URL-equity.

**Gating-kriterier — alla måste vara klara INNAN `/trafik/olycka/{tv_id}`
indexeras:**

1. **Källa-attribuering** synlig på sidan: "Källa: Trafikverket" + länk till
   Trafikverkets `WebLink` (om finns) eller `https://trafikinfo.trafikverket.se/`.
   Ger E-E-A-T och good-faith mot datakällan.
2. **Editorial intro-text** på sidan (min 150 ord per incident-typ-mall) —
   vad är detta, varför visar vi det, vad gör läsaren med info. Annars
   "limited original content"-flagga från AdSense.
3. **Schema.org `SpecialAnnouncement`** + `Place` per incident — strukturerad
   data så Google förstår att detta inte är en NewsArticle.
4. **Ad-block-flagga** vid `SeverityCode >= 4` ELLER keyword-match på
   `död|avliden|dödad|allvarlig` i `Header`/`Message`. ~50 % av Deviations
   saknar SeverityCode → keyword-fallback obligatorisk.
5. **MessageType låses vid first-write.** Om en `Trafikmeddelande · Vägen
avstängd` senare uppgraderas till `Olycka` av Trafikverket: behåll
   ursprungstypen. Annars retroflyttas raden till permalink-rymden mid-life
   och retention-policy ändras med bibehållen URL.

**SEO-risk accepterad:** 270–900 thin-content-permalinks utan AI-rewrite
kan rankas svagt och påverka domänbetyg. Användarens beslut: värdet av
sökbar olyckshistorik (validerat mot GSC: ~750 clicks/28d på `olycka`-queries
som komplement till polishändelser) väger upp risken. Mätperiod 90d post-
launch — om aggregerad CTR/position på domänen försämras → överväga `noindex`
eller helt avveckla permalinks.

### 4. Vad importeras till `events`-tabellen

**Importera allt utom Färjor och Suspended.** Två retention-regler, inte fyra.

| MessageType                 | Importeras? | Retention efter `EndTime`         | Permalink? | Indexerad? |
| --------------------------- | :---------: | --------------------------------- | :--------: | :--------: |
| **Olycka**                  |     Ja      | **90 d** (matchar `crime_events`) |     Ja     |     Ja     |
| Trafikmeddelande            |     Ja      | 30 d                              |    Nej     |  Aggregat  |
| Hinder                      |     Ja      | 30 d                              |    Nej     |  Aggregat  |
| Vägarbete                   |     Ja      | 30 d                              |    Nej     | Live-karta |
| Restriktion                 |     Ja      | 30 d                              |    Nej     | Live-karta |
| Viktig trafikinformation    |     Ja      | 30 d                              |    Nej     |  Aggregat  |
| **Färjor**                  |   **Nej**   | —                                 |     —      |     —      |
| **`Suspended=true`** (alla) |   **Nej**   | —                                 |     —      |     —      |

**Logik:**

- **Olycka** är journalistiskt intressant content → samma retention som polishändelser.
  Volymbaseline 3–10/dygn → 270–900 rader/90d. Trivial DB-belastning.
- **Övriga importerade typer** får enhetlig 30d retention. Pruning-komplexitet
  inte värt eventuell DB-besparing — sample-volym 1245 aktiva, steady-state
  efter 30d retention ~5–10k rader. Trivialt.
- **Färjor** filtreras vid import — brand-mismatch, inget värde för Brottsplatskartan.
- **Suspended** är operativt avstängd data, ej newsworthy.

**Pruning-jobb (2 regler, kör 1×/dygn):**

```sql
-- Olycka: 90d efter end_time
DELETE FROM events
WHERE source = 'trafikverket'
  AND message_type = 'Olycka'
  AND end_time IS NOT NULL
  AND end_time < NOW() - INTERVAL 90 DAY;

-- Övriga: 30d efter end_time
DELETE FROM events
WHERE source = 'trafikverket'
  AND message_type != 'Olycka'
  AND end_time IS NOT NULL
  AND end_time < NOW() - INTERVAL 30 DAY;
```

Aktiva (`end_time IS NULL` eller framtid) påverkas aldrig.

### 5. Dedup mot Polisens RSS — **soft först, hide senare**

"Trafikolycka" finns i båda källorna. Tröskel-värdet 500 m / 30 min är inte
empiriskt validerat — på motorvägar kan två singelolyckor ligga 400 m isär
samma minut.

**Fas 1: soft dedup (logg only)**

- Vid import: kör match-jobbet och sätt `events.related_event_id` på
  Trafikverket-rader som matchar en `crime_event` inom 500 m / 30 min.
- **Dölj inget i UI.** Båda källor visas som idag — användaren ser dubletter.
- Logga matches i 4 veckor (`Log::info` med detaljer + match-distans + tid-diff).
- Mät false-positive-rate manuellt: stickprov 30 matches per vecka, kolla om
  de verkligen är samma incident.

**Fas 2: auto-hide (om data motiverar)**

- Om false-positive-rate är < 5 %: aktivera dölj-Trafikverket-vid-match i UI.
- Om > 5 %: justera tröskel (kanske 300 m / 15 min) eller behåll soft-dedup.
- Polisens rad har AI-rewrite + permalänk → den prioriteras vid auto-hide.

**Volymbaseline:** ~3–10 olyckor/dygn från Trafikverket vs ~50–100 från
Polisen. Förväntad träfffrekvens i dedup-jobbet: ~1–3/dygn (där samma
olycka rapporteras av båda). Manuell stickprovskontroll är hanterbar.

## Förslag

### Schema (utgår från alternativ B — `events`-tabell)

Vid alternativ A: byt tabellnamn till `trafikverket_deviations` och
ta bort `source`-kolumnen. Övriga fält samma.

Faktiska MessageType-värden enligt v1.6-XSD: `"Viktig trafikinformation"`,
`"Färjor"`, `"Hinder"`, `"Olycka"`, `"Restriktion"`, `"Trafikmeddelande"`,
`"Vägarbete"`. **"Vilt" finns inte som MessageType** — vilt rapporteras
som `MessageCode` under "Hinder" eller "Trafikmeddelande". `"Färjor"`
filtreras bort i fas 1 (brand-mismatch).

```sql
CREATE TABLE events (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source VARCHAR(32) NOT NULL,                -- 'trafikverket', framtida 'smhi', 'krisinfo'
  external_id VARCHAR(64) NOT NULL,           -- Trafikverkets Deviation.Id
  parent_external_id VARCHAR(64) NULL,        -- Situation.Id (för intern dedup vid render)
  message_type VARCHAR(64) NOT NULL,          -- 'Olycka', 'Vägarbete', 'Hinder', 'Trafikmeddelande', 'Restriktion', 'Viktig trafikinformation'.
                                              -- LÅSES vid first-write — ändras inte vid UPSERT (annars retroflyttas raden mellan retention-policies).
  message_code VARCHAR(64) NULL,              -- finkornig: 'Beläggningsarbete', 'Broöppning', 'Djur på vägen'
  severity_code TINYINT NULL,                 -- 1, 2, 4, 5 (ej 3). ~50 % null — komplettera med keyword-match vid ad-block-flagga
  suspended BOOLEAN NOT NULL DEFAULT FALSE,   -- v1.6-fält; importeras aldrig om TRUE, men flagga ifall fältet flippar mid-life
  last_seen_active_at TIMESTAMP NULL,         -- senaste fetch där raden hade Suspended=false; safety net för debugging
  icon_id VARCHAR(64) NULL,                   -- 'accident', 'roadwork', etc. — för marker-rendering
  message TEXT NULL,
  location_descriptor TEXT NULL,
  road_number VARCHAR(32) NULL,               -- 'E4', '73'
  county_no SMALLINT NULL,                    -- första/primära värdet från CountyNo[]; 2 mappas till 1; 0/0 ignoreras
  administrative_area_level_1 VARCHAR(64) NULL, -- 'Stockholms län' — derived från county_no
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  start_time TIMESTAMP NOT NULL,              -- kan vara år bakåt på pågående vägarbeten
  end_time TIMESTAMP NULL,                    -- kan vara år framåt; ValidUntilFurtherNotice → NULL
  created_time TIMESTAMP NOT NULL,
  modified_time TIMESTAMP NOT NULL,
  related_event_id BIGINT UNSIGNED NULL,      -- soft dedup: pekar på matchande crime_event (loggas, döljs ej i UI fas 1)
  source_url VARCHAR(500) NULL,               -- Trafikverkets WebLink om finns
  payload JSON NULL,                          -- källspecifika fält som inte hör hemma i kolumner; spekulativa
                                              -- fält (CountyNo[], AffectedDirection, ManagedCause, RoadName, Creator,
                                              -- ValidUntilFurtherNotice, CountryCode, Geometry.Line.WKT) lagras här
                                              -- tills konkret use case dyker upp
  imported_at TIMESTAMP NOT NULL,
  UNIQUE KEY idx_source_external (source, external_id),
  KEY idx_geo (lat, lng),
  KEY idx_time (start_time, end_time),
  KEY idx_source_active (source, end_time),
  KEY idx_county_time (county_no, start_time)
);
```

**Aktiv-query:** `WHERE source='trafikverket' AND (end_time IS NULL OR end_time > NOW())`.
`Suspended=true` filtreras vid import — finns aldrig i tabellen.

**Viktigt:** **en rad per Deviation, inte per Situation.** Trafikverkets
Situation är en container med flera Deviations som var och en har egen
koordinat och tid.

**Skär-djupt-motivering (review 2026-05-03):** Tidigare schemautkast hade 7
spekulativa kolumner (`creator`, `affected_direction`, `managed_cause`,
`valid_until_further_notice`, `country_code`, `road_name`, `geometry_wkt`)
som inte används i renderlogiken. YAGNI → flyttat till `payload JSON`.
Migration kan lägga till kolumner senare när konkret use case finns.

**CountyNo-edge case (review 2026-05-03):** XSD säger `<xs:element maxOccurs="unbounded">` —
gränsöverskridande situations (t.ex. E4 över Stockholm/Uppsala län) ger
`CountyNo: [1, 3]`. Vårt schema tar första värdet i `county_no`-kolumnen,
hela arrayen lagras i `payload.CountyNo`. `2 → 1` (deprecated länkkod).
**Verifiera mot live-data innan migration deployas** — om gränsöverskridande
visar sig vanligt, lyft till separat `event_counties`-tabell.

### Import-jobb

```bash
docker compose exec app php artisan trafikverket:fetch
```

Schemaläggs i `app/Console/Kernel.php` **var 5:e minut** (samma kadens som
`crimeevents:fetch`). Tightare schedule (2 min) sänktes vid arkitektur-review:
användaren ser inte skillnad mellan 2 och 5 min på en olycka som varar 1–2 h,
och 2 min skulle ge ~21M write-ops/månad mot MariaDB. Sänk bara om mätning
visar att stale data är ett konkret problem.

Idempotent på `(source, external_id)` — uppdatera vid `modified_time`-bump.
**`message_type` låses vid first-write** — ändras aldrig vid UPSERT (annars
retroflyttas raden mellan retention-policies med bibehållen URL).

**Suspended → import:** rader med `Suspended=true` filtreras bort i query
**OCH** efter response-parsing (defense in depth). När en rad som tidigare
var aktiv flippar till Suspended: behåll i tabellen, sätt `last_seen_active_at`,
filtrera ur live-aggregat. Möjliggör debugging av Suspended-flippar utan
att tappa historik.

**Rate-limit / failure-strategi:**

- Logga HTTP-status + responstid per fetch.
- 200 → reset failure-counter, fortsätt.
- 429 / 5xx / connection error → exponential backoff (1 → 5 → 30 min → paus 1 h).
- 401 / 403 → omedelbart larm via befintlig kanal (`Log::error` + ev. mail
  till admin) — nyckel revokad.
- Mäta `failure_count` + `last_success_at` exposed via health-check så
  oncall ser om feeden är trasig en lördagskväll.

### Pruning

Två regler — Olycka 90 d, övriga 30 d. Se "4. Vad importeras" ovan för SQL.

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
- **Default OFF** initialt — ~1245 aktiva Trafikverket-Situations gör
  kartan stökigare än befintliga användare är vana vid. Toggle synlig
  och tydlig. Ändra default till PÅ senare om feedback är positiv.
- **Markercluster är gating, inte nice-to-have.** 1245 markers utan
  clustering = LCP-tank på mobil + CLS vid layer-toggle. Acceptance-
  kriterium före layer-toggle aktiveras.
- **`min-height` på kart-container** fastställd före JS körs (CLS-skydd).
- **Egen sida `/trafik`** — Fas 2, inte Fas 1. Kräver editorial intro-text
  före launch (annars AdSense "limited original content").

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

- **API-nyckel-rotation.** Nyckel registrerad 2026-05-03, ingen utgångstid
  satt. Rate-limits ej publicerade — Trafikverket "övervakar och hör av sig"
  vid överskridning.
- **Volym.** Sverige-snapshot 2026-05-03: 1 245 aktiva Situations / 2 760
  Deviations efter Suspended-filter. Stationär nivå långsiktigt eftersom
  Vägarbeten dominerar (803/2 760 = 29 %) och de är mestadels stationära.
  Med pruning enligt importpolicy: ~5 000–10 000 rader steady-state.
- **Olyckor är sällsynta i realtid.** 3 aktiva olyckor i hela Sverige vid
  testtillfället; 0 i Stockholm. Trafikverket rapporterar bara olyckor som
  **påverkar trafiken** — detta är bra (selektiv hög-kvalitetsdata, lågt
  brus) men betyder att Polisens RSS fortsatt täcker majoriteten av blåljus-
  olyckor (~50–100/dygn vs ~3–10/dygn från Trafikverket).
- **SeverityCode-täckning är dålig.** ~50 % av Deviations saknar klassning.
  Ad-block-flagga måste komplettera `SeverityCode >= 4` med keyword-match
  på "död"/"avliden"/"allvarlig" i `Header`/`Message`.
- **Multi-year vägarbeten.** Sett aktivt vägarbete med `StartTime=2020-09-01`
  och `EndTime=2026-08-31`. För Vägarbete-typen radera vid `EndTime`
  (ingen retention) — `start_time` får aldrig visas som "händelsedatum"
  för UX, vissa är flera år gamla.
- **CountyNo=2 (deprecated)** returnerar fortfarande data, dubblar Stockholm.
  Mappa `2 → 1` (eller dropp) vid import.
- **Norrbotten/Västerbotten-bias.** ~50 % av rikets aktiva Situations ligger
  i de fyra nordliga länen. Söktrafiken där är låg → `/lan/norrbottens-lan/trafik`
  blir rik content men låg-volym. Inte en risk, bara en observation.
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

**Hög** för Fas 1 (live-karta). Källan är välbeskriven, formatet verifierat
mot live-data, schema är skuret djupt enligt YAGNI. Implementation Fas 1:
1–2 dagar.

**Medel** för Fas 3 (permalinks). SEO-risken är användar-accepterad men
mätbar — mätperiod 90 d post-launch avgör om strategin håller. Tunn content
utan AI-rewrite kan rankas svagt och påverka domänbetyg.

## Beroenden mot andra todos

- Inga blockerare för Fas 1.
- **Synergi med #51** — `events`-tabellen är återanvändbar för SMHI/
  räddningstjänst/krisinfo utan ytterligare schema-arbete.

## Arkitektur-review 2026-05-03 — slutsatser

Subagent-review identifierade 15 findings; 14 åtgärdas i fas-uppdelningen ovan.
Sammanfattning av tagna beslut:

- **Schema skuret djupt** — 7 spekulativa kolumner flyttade till `payload JSON`.
  Migrera kolumner senare när konkret use case finns.
- **Retention-policy förenklad** — 4 regler → 2 regler (90d Olycka, 30d övriga).
- **CountyNo[]-edge case åtgärdad** — primär i kolumn, hela arrayen i `payload`.
- **Schedule 5 min, inte 2 min** — sänkt write-belastning utan UX-impact.
- **Soft dedup först** — log only i Fas 1, auto-hide i Fas 2 om data motiverar.
- **Markercluster är gating** — acceptance-kriterium före launch.
- **Rate-limit/backoff-strategi** — inbyggd från start.
- **MessageType låses vid first-write** — förhindrar retroaktiva retention-byten.
- **`last_seen_active_at`** lagts till för Suspended-debugging.
- **Permalinks behålls i Fas 3** (användarens beslut) — gating-kriterier
  (källa-attribuering, editorial text, Schema.org, ad-block-flagga)
  obligatoriska före indexering.
- **GDPR regnummer-maskning avskriven** — Trafikverket är myndighet och
  publicerar operativa trafikmeddelanden, inga fordonsidentifierande detaljer.
- **Fas-uppdelning** — minimal Fas 1 → aggregat Fas 2 → permalinks Fas 3,
  med mätbara gates emellan.

## Fas-uppdelning

Hela todon var tidigare skissad som "implementera allt på en gång" (3–4
dagar). Arkitektur-reviewen 2026-05-03 plockade upp att det blandar för
många rörliga delar (DB-schema, import-job, dedup, SEO-aggregat, layer-UI,
permalinks, AdSense-flaggor) och ger för stor rollback-risk om något
flaggas. Delas upp i tre faser med konkreta gates emellan.

### Fas 1 — minimal live-karta (1–2 dagar)

**Mål:** Trafikverket-data syns på huvudkartan (`/karta`) som extra layer.
Inga nya routes, inga permalinks, ingen `/trafik`-sida.

- Migration: `events`-tabell med ~15 kolumner (se schema-blocket).
- `App\Models\Event` + ev. helper.
- `php artisan trafikverket:fetch` — 5 min schedule, idempotent UPSERT,
  filtrera `Suspended=true` och `Färjor` vid query, rate-limit/backoff-logik.
- `php artisan trafikverket:prune` — 1×/dygn, två retention-regler.
- `/api/eventsMap` får `?source=polisen|trafikverket|all` med default `polisen`
  för bakåtkompat.
- Frontend: ny Leaflet-layer (`L.markerClusterGroup`), toggle default **OFF**,
  ikoner per `MessageType`.
- `min-height` på kart-container; markercluster aktivt från start.

**Gate till Fas 2 (1 vecka soak):**

- Volym stationär: ingen DB-tillväxt-explosion utöver baseline.
- Ingen CWV-regression på `/karta` (LCP/CLS jämfört med pre-deploy).
- Ingen rate-limit-trippning från Trafikverket.
- Soft-dedup-loggar visar < 5 % false-positives på manuellt stickprov.

### Fas 2 — aggregat-sidor (1–2 dagar, efter gate)

**Mål:** `/trafik` + `/{lan}/trafik` + `/trafikolyckor` + `/{lan}/trafikolyckor`
som aggregat-vyer. Mixar Trafikverket + polishändelser enligt `parsed_title`-
filter-set.

- Routes + controllers.
- **Editorial intro-text** per sid-typ (min 300 ord på `/trafik`, min 150 ord
  per `/{lan}/trafik`-mall) — skrivs _före_ deploy. Skickar inte tunna sidor
  till Google.
- **`noindex` initialt** på alla `/trafik*`-routes. Lyfts manuellt en sida i
  taget när text är granskad.
- Internlänk från `/lan/{lan}` → `/lan/{lan}/trafik`.
- Auto-hide vid dedup-match aktiveras (om Fas 1-soak motiverar).

**Gate till Fas 3 (4 v efter Fas 2):**

- GSC-mätning: aggregat-sidor visar impressions, position < 30.
- AdSense: inga "limited original content"-flaggor på `/trafik*`.
- Ingen domänövergripande CTR/position-regression.

### Fas 3 — permalinks för `Olycka` (efter gates)

**Mål:** `/trafik/olycka/{tv_id}` indexeras med 90d retention sedan 410.

- Permalinks rendrar `Olycka`-rader från events-tabellen.
- Schema.org `SpecialAnnouncement` + `Place`.
- Källa-attribuering ("Källa: Trafikverket" + `WebLink`).
- Ad-block-flagga vid `SeverityCode >= 4` ELLER keyword-match på
  `död|avliden|dödad|allvarlig`.
- Sitemap-utbyggnad — endast aktiva permalinks listas.
- 410-strategi: pruning-jobbet skickar `Status: 410 Gone` på utgångna IDs
  (ej 404) så Google avindexerar snabbare.

**90-dagars mätperiod efter Fas 3:**

- Aggregat: visar permalinks rankning.
- Domänbetyg: ingen regression i GSC-aggregat.
- AdSense RPM/CPM på `/trafik/olycka/*` jämfört med domänsnitt — om < 50 % är
  innehållet för tunt; överväg `noindex` eller avveckla.

## Inte i scope

- **Vägkamera-bilder** — Trafikverket har bilder men det är annan
  integration (lagring, GDPR-fundering).
- **Historisk olycksdata** — täcks av #40 (STRADA).
- **Vägkvalitet/asfaltsstatus** — inte Brottsplatskartans scope.
- **WebSocket push** — REST-polling var 2:a minut räcker; pushen är
  värd att överväga först om realtidskravet skärps.
