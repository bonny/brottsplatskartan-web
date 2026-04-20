# Claude TODO - Brottsplatskartan

Statusdokument för pågående förbättringsarbete.

---

## Översikt av uppdrag

1. **Minska antalet URLer/vyer** - Response cache tar för stor plats
2. **SEO-review** - Sajten tjänar pengar på annonser, måste ranka högt på Google
3. **Konsolidera blade-templates** - Framförallt event-kort på startsidan

---

## 1. Minska URLer och cache-påverkan

### Datavolym i databasen
| Data | Antal |
|------|-------|
| Totalt händelser | 296,878 |
| Unika dagar | 2,849 (~10 år) |
| Unika orter | 330 |
| Unika län | 21 (+ 2 dubletter) |
| Unika brottstyper | 119 |
| Locations-poster | 687,858 |

### Cache-konfiguration (Spatie Response Cache)
| Route | TTL |
|-------|-----|
| `/` (startsida) | 2 min |
| `/vma`, `/api/vma` | 2 min |
| `/handelser/{date}` (>7 dagar gamla) | 7 dagar |
| `/api/events` | 10 min |
| Övriga routes | 30 min |

### 🔴 PROBLEMOMRÅDEN - Potentiella cache-entries

| Route | Beräkning | Antal entries | Allvarlighetsgrad |
|-------|-----------|---------------|-------------------|
| `/plats/{plats}/handelser/{date}` | 330 orter × 2849 dagar | **~940,000** | 🔴 KRITISK |
| `/lan/{lan}/handelser/{date}` | 21 län × 2849 dagar | **~60,000** | 🟠 HÖG |
| `/handelser/{date}` | 2849 dagar | **~2,849** | 🟢 OK (lång TTL) |
| `/plats/{plats}` | 330 orter | **~330** | 🟢 OK |
| `/typ/{typ}` | 119 typer × pagination | **~500+** | 🟡 MEDEL |
| `/lan/{lan}` | 21 län | **~21** | 🟢 OK |
| `/{city}` | fallback, okänt antal | **?** | 🟡 UNDERSÖK |

**Total potentiell cache-storlek: >1,000,000 entries**

### 🎯 Rekommendationer

#### 1. Ta bort `/plats/{plats}/handelser/{date}` (KRITISK)
- **Skapar ~940,000 cache-entries!**
- Användare kan nå samma data via `/handelser/{date}` + filtrera
- Alternativ: Lägg till `noindex` + exkludera från cache

#### 2. Ta bort `/lan/{lan}/handelser/{date}` (HÖG)
- **Skapar ~60,000 cache-entries**
- Samma lösning: `/handelser/{date}` är tillräckligt

#### 3. Granska `/{city}` catch-all routen
- Verkar överlappa med `/plats/{plats}`
- Kan skapa duplicerade cache-entries

#### 4. Lägg till cache-exkludering för datum-routes
Om routes ska behållas, exkludera dem från response cache:
```php
// I BrottsplatskartanCacheProfile
public function shouldCacheRequest(Request $request): bool
{
    // Exkludera datum-kombinationer med plats/län
    if ($request->is('plats/*/handelser/*') || $request->is('lan/*/handelser/*')) {
        return false;
    }
    return parent::shouldCacheRequest($request);
}
```

### Status
- [x] Analysera datavolym i databasen
- [x] Identifiera vilka routes som skapar flest cache-entries
- [x] Beräkna potentiell cache-storlek
- [ ] **AVVAKTAR** - Beslut om vilka routes som ska tas bort/ändras

---

## 2. SEO-review

### Status
- [ ] Granska meta-taggar (title, description)
- [ ] Kontrollera canonical URLs
- [ ] Schema.org markup (JSON-LD)
- [ ] Intern länkning
- [ ] Page speed / Core Web Vitals
- [ ] Mobile-first indexering
- [ ] robots.txt och sitemap

---

## 3. Blade-templates - Event-kort

### Nuläge: 8 olika event-kort-templates

| Template | Används av | Rader | Beskrivning |
|----------|------------|-------|-------------|
| `crimeevent.blade.php` | Detaljsidor, design | ~175 | Original, stort kort med karta |
| `crimeevent_v2.blade.php` | ? | ~115 | Nyare två-kolumn version |
| `crimeevent-small.blade.php` | Listor | ~60 | Miniatyr med thumbnail |
| `crimeevent-city.blade.php` | Stadssidor | ~20 | Likt small |
| `crimeevent-mapless.blade.php` | ? | ~30 | Utan karta |
| `crimeevent-hero.blade.php` | Startsida (topp 3) | ~23 | Stor hero |
| `crimeevent-hero-second.blade.php` | Startsida (row 2) | ~20 | Medium hero |
| `crimeevent-helicopter.blade.php` | Helikoptersidan | ~45 | Specialversion |

### Startsidans struktur (`events-heroes.blade.php`)
1. **3 st stora heroes** → `crimeevent-hero`
2. **6 st medium (2x3 rutnät)** → `crimeevent-hero-second`
3. **8 st små i lista** → `crimeevent-small`

### Designsidan (`/design`)
✅ **KLAR** - Visar nu alla 9 kortvarianter:
1. `parts.crimeevent` (single=true)
2. `parts.crimeevent` (overview=true)
3. `parts.crimeevent_v2` (overview=true)
4. `parts.crimeevent-hero`
5. `parts.crimeevent-hero-second`
6. `parts.crimeevent-small` (detailed=true)
7. `parts.crimeevent-small` (detailed=false)
8. `parts.crimeevent-city`
9. `parts.crimeevent-mapless`
10. `parts.crimeevent-helicopter`

### Förslag för konsolidering
1. ~~**Uppdatera designsidan** att visa ALLA kort-varianter~~ ✅
2. **Identifiera duplicering** mellan `crimeevent-city` och `crimeevent-small`
3. **Bestäm strategi** för `crimeevent` vs `crimeevent_v2`

### Status
- [x] Inventera alla event-templates
- [x] Uppdatera designsidan med alla varianter
- [ ] Besluta vilka kort att behålla

### Ändringar gjorda
- Uppdaterade `/design`-sidan (`resources/views/design.blade.php`) för att visa alla korttyper
- Varje kort visas i en sektion med:
  - Template-namn (t.ex. `parts.crimeevent-hero`)
  - Beskrivning av var det används
  - Live-rendering av kortet
- La till dokumentation i `AGENTS.md` om att rensa cache vid blade-ändringar

---

## Nästa steg

**Status: AVVAKTAR**

Alla uppgifter pausade tills vidare. Färdiga analyser:

1. ✅ **Designsidan** - Visar nu alla kortvarianter på `/design`
2. ✅ **Cache-analys** - Identifierade problemroutes (se sektion 1)
3. ⏸️ **SEO-review** - Ej påbörjad
4. ⏸️ **Blade-konsolidering** - Väntar på beslut om vilka kort som ska behållas

---

## 4. Uppdatera mbtiles till nyare version

Nuvarande fil: `2017-07-03_europe_sweden.mbtiles` (~1.21 GB, från 2017).
Ligger i Hetzner Object Storage, laddas ner via `deploy/download-tiles.sh`.

**Behöver undersökas:**
- Hur genererades filen från början? (OSM-extract + tippecanoe? Planetiler?
  Annan pipeline?)
- Finns script/dokumentation från första gången någonstans? Kolla
  `../brottsplatskartan-tileserver` och nvALT.
- Vilket stylesheet/schema använder tileserver-gl för att rendera? Ny
  mbtiles kan behöva matchande style.
- Hur ofta uppdateras OSM-data tillräckligt mycket för att motivera
  en ny extract? (Vägar i Sverige ändras långsamt — kanske vart 2–3:e år.)

**Pipeline-skiss (att verifiera):**
1. Ladda ner senaste Sverige-extract från Geofabrik
   (`https://download.geofabrik.de/europe/sweden-latest.osm.pbf`)
2. Konvertera till mbtiles med `planetiler` eller `tilemaker`
3. Ladda upp till Hetzner Object Storage-bucket `brottsplatskartan/tiles/`
4. Uppdatera `TILES_FILE` + `TILES_URL` i `deploy/download-tiles.sh`
5. Deploy — containern plockar upp nya filen

### Status
- [ ] Hitta/dokumentera ursprunglig pipeline
- [ ] Testa ny extract med planetiler eller tilemaker
- [ ] Verifiera att tileserver-gl:s default-style fungerar med ny mbtiles
- [ ] Committa dokumentation till `deploy/update-tiles.md` eller liknande

---

## 5. Uppgradera spatie/laravel-responsecache 7.7 → 8.x (SWR-stöd)

**Att göra EFTER Hetzner-cutover** — inte innan, för att undvika
rörliga delar mitt i flytten.

### Varför

Version 8.0 (feb 2025) lade till **flexible caching**: stale responses
serveras direkt medan cachen regenereras i bakgrunden. Exakt
SWR-mönstret som `Cache::flexible` använder internt, men på
response-cache-nivå (hela HTML-svaret).

Löser konkret problem: `/stockholm` är seg ibland när outer response
cache expirerar (30 min TTL) och användaren måste vänta på hela
regenereringen (geo-spatial query + stats-aggregering + Blade).

### Krav (vi uppfyller)

- PHP 8.4+ ✅
- Laravel 12+ ✅

### Migration

1. `composer require spatie/laravel-responsecache:^8.0`
2. Uppdatera `BrottsplatskartanCacheProfile` för SWR-API (fresh/stale-fönster)
3. Lokalt test: verifiera att stale-svar serveras inom grace-perioden
4. Deploy

### Förväntad vinst

- `/stockholm`, `/lan/*`, `/plats/*`, startsidan: nästan aldrig kall cache
- Dagens "30 min TTL utan SWR" → 25 min fresh + 5-15 min stale-window
- Eliminerar ~3s väntetider som nu drabbar enstaka användare var 30:e minut

### Status
- [ ] Vänta till efter cutover (DO-server avstängd)
- [ ] Läs migration-guide från Spatie
- [ ] Implementera + testa lokalt
- [ ] Deploy och verifiera

---

*Senast uppdaterad: 2026-04-20*
