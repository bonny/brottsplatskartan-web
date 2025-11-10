# Test-URLs för att verifiera optimeringar

## Webbläsare-tester (http://localhost:8000)

### Datum-index fixes (navigering mellan dagar)

1. **Startsida**
   - http://localhost:8000/
   - Klicka på "föregående dag" / "nästa dag" länkar

2. **Län-sidor**
   - http://localhost:8000/lan/stockholms-lan
   - http://localhost:8000/lan/vastra-gotalands-lan
   - Testa navigering mellan dagar

3. **Plats-sidor**
   - http://localhost:8000/plats/stockholm
   - http://localhost:8000/plats/goteborg
   - Testa navigering mellan dagar

### N+1 fixes (kartvy)

4. **Karta**
   - http://localhost:8000/karta/
   - Använder `/api/eventsMap` endpoint (N+1 fix)

---

## API-tester (curl/terminal)

### N+1 optimerade endpoints

```bash
# 1. API Events (med filtrering)
curl "http://localhost:8000/api/events?limit=20"
curl "http://localhost:8000/api/events?area=Stockholms+län&limit=10"
curl "http://localhost:8000/api/events?type=Inbrott&limit=10"

# 2. API Events Map (för kartvisning)
curl "http://localhost:8000/api/eventsMap"

# 3. Hämta specifik händelse
curl "http://localhost:8000/api/event/296231"

# 4. Händelser nära koordinat (Stockholms centralstation)
curl "http://localhost:8000/api/eventsNearby?lat=59.3293&lng=18.0686&distance=5&limit=10"

# 5. Lista alla län
curl "http://localhost:8000/api/areas"
```

---

## Query-count verifiering

```bash
# Kör test-script för att se antal queries
php test-query-count.php
```

**Förväntat resultat:**
- Test 1-2: 2-3 queries (✅ BRA)
- Test 3: 0-1 queries (cache kan vara aktiv)
- Test 4: ~21 queries (visar problemet FÖRE fix)

---

## Med jq för snyggare output

```bash
# Visa endast rubriker
curl -s "http://localhost:8000/api/events?limit=5" | jq '.data[] | .headline'

# Visa händelser med plats
curl -s "http://localhost:8000/api/events?limit=5" | jq '.data[] | {headline, location_string, date_human}'

# Räkna antal händelser på kartan
curl -s "http://localhost:8000/api/eventsMap" | jq '.data | length'

# Visa alla län
curl -s "http://localhost:8000/api/areas" | jq '.data[]'
```

---

## Förväntat resultat

### ✅ Datum-index fixes
- Sidorna laddar snabbt
- Navigering mellan dagar fungerar smidigt
- Inga felmeddelanden

### ✅ N+1 fixes  
- API returnerar JSON med händelser
- Varje händelse har `location_string` ifyllt
- Snabb respons (< 1 sekund)
- Query-count: 2-3 queries istället för 20-500

### ✅ Query-count test
- Test 1-2 visar låga värden (2-3 queries)
- Test 4 visar höga värden (21 queries) = problemet vi fixade

---

## Troubleshooting

**Om API returnerar 404:**
- Kontrollera att servern körs: `php artisan serve`
- URL ska vara `/api/events` (utan version)

**Om query-count är 0:**
- Cache är aktiv, vilket är normalt
- Queries körs bara första gången, sedan hämtas från cache

**Om sidor laddar långsamt:**
- Rensa cache: `php artisan cache:clear`
- Kontrollera MariaDB status med `htop` eller `top`
