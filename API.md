# Brottsplatskartan API Dokumentation

## Bas-URL

**Lokal utveckling:** `http://localhost:8000/api`
**Produktion:** `https://brottsplatskartan.se/api`

Alla API-endpoints returnerar JSON-data.

---

## Endpoints

### 1. GET `/api/events`

Hämta händelser med filtrering och paginering.

**Optimerad med:** N+1 fix (eager loading av locations)

**Query-parametrar:**
- `limit` (integer, default: 10) - Antal händelser per sida
- `area` (string, optional) - Län, t.ex. "Stockholms län"
- `location` (string, optional) - Plats/gata, t.ex. "Folkungagatan"
- `type` (string, optional) - Händelsetyp, t.ex. "Inbrott", "Rån"
- `page` (integer, default: 1) - Sidnummer för paginering

**Exempel:**
```bash
# Alla händelser (10 st)
curl "http://localhost:8000/api/events"

# Händelser i Stockholms län
curl "http://localhost:8000/api/events?area=Stockholms+län&limit=20"

# Inbrott i Stockholm
curl "http://localhost:8000/api/events?type=Inbrott&area=Stockholms+län"

# Med paginering
curl "http://localhost:8000/api/events?page=2&limit=50"
```

**Svar-struktur:**
```json
{
  "links": {
    "current_page": 1,
    "first_page_url": "...",
    "last_page": 29617,
    "next_page_url": "...",
    "prev_page_url": null,
    "per_page": 10,
    "total": 296161
  },
  "data": [
    {
      "id": 296231,
      "title_type": "Mord/dråp",
      "title_location": "Stockholm",
      "headline": "Flera personer ringer polisen...",
      "description": "...",
      "content": "...",
      "location_string": "Riksby, Bromma, Stockholm, Stockholms län",
      "lat": 59.3444841,
      "lng": 17.94539,
      "date_human": "4 dagar sedan",
      "permalink": "http://localhost:8000/...",
      "image": "https://kartbilder.brottsplatskartan.se/...",
      "external_source_link": "https://polisen.se/..."
    }
  ]
}
```

---

### 2. GET `/api/eventsMap`

Hämta händelser optimerade för kartvisning (senaste 3 dagarna, max 500 st).

**Optimerad med:** N+1 fix (eager loading av locations)
**Cache:** 5 minuter

**Query-parametrar:** Inga

**Exempel:**
```bash
curl "http://localhost:8000/api/eventsMap"
```

**Svar-struktur:**
```json
{
  "data": [
    {
      "id": 296231,
      "time": "17:37",
      "time_human": "4 dagar sedan",
      "headline": "Flera personer ringer polisen...",
      "type": "Mord/dråp",
      "locations": "Riksby, Bromma, Stockholm",
      "lat": 59.3444841,
      "lng": 17.94539,
      "image": "https://kartbilder.brottsplatskartan.se/...",
      "permalink": "http://localhost:8000/..."
    }
  ]
}
```

---

### 3. GET `/api/event/{eventID}`

Hämta en enskild händelse baserat på ID.

**Query-parametrar:** Inga (ID i URL:en)

**Exempel:**
```bash
curl "http://localhost:8000/api/event/296231"
```

**Svar:** Samma struktur som ett element i `/api/events` data-arrayen.

---

### 4. GET `/api/eventsNearby`

Hämta händelser nära en specifik koordinat.

**Query-parametrar:**
- `lat` (float, required) - Latitud
- `lng` (float, required) - Longitud
- `distance` (integer, optional, default: 25) - Avstånd i kilometer
- `limit` (integer, optional, default: 10) - Max antal händelser

**Exempel:**
```bash
# Händelser nära Stockholms centralstation
curl "http://localhost:8000/api/eventsNearby?lat=59.3293&lng=18.0686&distance=5&limit=20"
```

---

### 5. GET `/api/areas`

Hämta lista över alla län (administrative_area_level_1).

**Query-parametrar:** Inga

**Exempel:**
```bash
curl "http://localhost:8000/api/areas"
```

**Svar:**
```json
{
  "data": [
    "Stockholms län",
    "Västra Götalands län",
    "Skåne län",
    ...
  ]
}
```

---

### 6. GET `/api/eventsInMedia`

Hämta händelser som har associerade nyhetsartiklar från TextTV.

**Query-parametrar:**
- `limit` (integer, optional, default: 10)

**Exempel:**
```bash
curl "http://localhost:8000/api/eventsInMedia?limit=20"
```

---

### 7. GET `/api/mostViewedRecently`

Hämta mest visade händelser nyligen.

**Query-parametrar:**
- `limit` (integer, optional, default: 10)
- `minutes` (integer, optional, default: 60) - Tidsperiod i minuter

**Exempel:**
```bash
curl "http://localhost:8000/api/mostViewedRecently?limit=20&minutes=120"
```

---

## Datamodell

### CrimeEvent (händelse)

**Huvudfält:**
- `id` (integer) - Unikt ID
- `title_type` (string) - Händelsetyp (t.ex. "Inbrott", "Rån", "Trafikolycka")
- `title_location` (string) - Primär plats
- `headline` (string) - AI-genererad rubrik eller fallback
- `description` (string) - Kort beskrivning/teaser
- `content` (string) - Fullständig text (HTML-formaterad)
- `content_teaser` (string) - Kortversion för listor

**Datum/tid:**
- `pubdate_iso8601` (string) - ISO 8601 format
- `pubdate_unix` (integer) - Unix timestamp
- `date_human` (string) - Relativ tid (t.ex. "4 dagar sedan")

**Geografisk data:**
- `lat` (float) - Latitud
- `lng` (float) - Longitud
- `location_string` (string) - Fullständig platsträng med län
- `location_string_2` (string) - Kortare platsträng utan län
- `administrative_area_level_1` (string) - Län
- `administrative_area_level_2` (string|null) - Kommun

**Viewport (för kartbilder):**
- `viewport_northeast_lat` (float)
- `viewport_northeast_lng` (float)
- `viewport_southwest_lat` (float)
- `viewport_southwest_lng` (float)

**Bilder:**
- `image` (string) - Statisk kartbild (640x320px)
- `image_far` (string) - Kartbild med större zoom-out

**Länkar:**
- `permalink` (string) - Länk till händelsen på brottsplatskartan.se
- `external_source_link` (string) - Länk till original på polisen.se

---

## Prestanda & Optimeringar

### N+1 Query Fix (2025-11-10)

Följande endpoints har optimerats för att undvika N+1 query-problem:

- ✅ `/api/events` - Använder `->with('locations')`
- ✅ `/api/eventsMap` - Använder `->with('locations')`

**Före optimering:**
- `/api/events` med 20 händelser: ~22 queries
- `/api/eventsMap` med 500 händelser: ~501 queries

**Efter optimering:**
- `/api/events` med 20 händelser: ~3 queries (85% minskning)
- `/api/eventsMap` med 500 händelser: ~2 queries (99% minskning)

### Cache

**API-endpoint caching (2025-11-11):**
- `/api/events` - Cachad i 2 minuter (120 sekunder)
  - Cache-nyckel baserad på: area, location, type, page, limit
  - Förhindrar upprepade COUNT queries från `paginate()`
- `/api/eventsInMedia` - Cachad i 5 minuter (300 sekunder)
  - Cache-nyckel baserad på: media, page, limit
- `/api/eventsMap` - Cachad i 5 minuter (300 sekunder)

**Geografiska queries:**
- Vissa geografiska queries cachade i 9-15 minuter
- Datum-navigering cachad i 14-23 minuter

**Cache-implementation:**
- Använder Laravel Cache facade med Redis som backend
- Automatisk cache-invalidering efter TTL
- Unik cache-nyckel för varje parameter-kombination

---

## Rate Limiting

**Nuvarande status:** Ingen rate limiting implementerad (TODO)

**Rekommendation för produktion:**
- Implementera rate limiting för att undvika överbelastning
- Överväg API-nycklar för högre volymanvändning

---

## CORS

API:et tillåter cross-origin requests (CORS är aktiverat).

---

## Felhantering

**Standard HTTP-statuskoder:**
- `200` - Lyckad förfrågan
- `404` - Händelse/resurs inte hittad
- `422` - Valideringsfel (felaktiga parametrar)
- `500` - Serverfel

**Fel-svar:**
```json
{
  "error": "Resource not found",
  "message": "Event with ID 999999 does not exist"
}
```

---

## Exempel-användning

### Hämta senaste inbrotten i Stockholm

```bash
curl "http://localhost:8000/api/events?type=Inbrott&area=Stockholms+län&limit=20" | jq '.data[] | {headline, location_string, date_human}'
```

### Hämta händelser på karta

```javascript
fetch('http://localhost:8000/api/eventsMap')
  .then(res => res.json())
  .then(data => {
    data.data.forEach(event => {
      // Lägg till marker på kartan
      addMarker(event.lat, event.lng, event.headline);
    });
  });
```

### Filtrera på flera parametrar

```bash
curl "http://localhost:8000/api/events?area=Västra+Götalands+län&type=Trafikolycka&limit=10&page=1"
```

---

## Kodexempel

### Python
```python
import requests

# Hämta händelser
response = requests.get('http://localhost:8000/api/events', params={
    'area': 'Stockholms län',
    'limit': 20
})

events = response.json()['data']
for event in events:
    print(f"{event['headline']} - {event['location_string']}")
```

### JavaScript/Node.js
```javascript
const axios = require('axios');

async function getEvents(area, limit = 10) {
  const response = await axios.get('http://localhost:8000/api/events', {
    params: { area, limit }
  });

  return response.data.data;
}

getEvents('Stockholms län', 20).then(events => {
  events.forEach(e => console.log(e.headline));
});
```

### PHP
```php
$area = 'Stockholms län';
$limit = 20;

$url = "http://localhost:8000/api/events?area=" . urlencode($area) . "&limit=$limit";
$response = file_get_contents($url);
$data = json_decode($response, true);

foreach ($data['data'] as $event) {
    echo $event['headline'] . "\n";
}
```

---

## Testsida

För att testa API:et lokalt, kör:

```bash
# Starta lokal server
php artisan serve

# Testa endpoints
curl http://localhost:8000/api/events
curl http://localhost:8000/api/eventsMap
curl http://localhost:8000/api/areas
```

---

## Changelog

### 2025-11-11
- ✅ Implementerat cache för `/api/events` (2 min TTL) - eliminerar upprepade COUNT queries
- ✅ Implementerat cache för `/api/eventsInMedia` (5 min TTL)
- ✅ 100% query-reduktion vid cache-träff (3 queries → 0 queries)
- ✅ Cache-nycklar unika per parameter-kombination

### 2025-11-10
- ✅ Fixat N+1 query problem i `/api/events` och `/api/eventsMap`
- ✅ Reducerat antal queries med 85-99%
- ✅ Förbättrad prestanda och minskad databas-belastning

### 2022-06-29
- ✅ Skapad virtuell kolumn `date_created_at` för optimerade datum-queries
- ✅ Lagt till index på datum-kolumner

---

## Support & Kontakt

För frågor eller buggrapporter, skapa ett issue på GitHub:
https://github.com/bonny/brottsplatskartan-web/issues
