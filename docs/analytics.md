# Analytics-data (GA4 + Search Console)

Hur Claude (och människor) kommer åt Brottsplatskartans GA4- och Search Console-data via två MCP-servrar:

- **`analytics-mcp`** — beteende på sajten (sessions, landingPages, deviceCategory, …)
- **`mcp-gsc`** — hur Google ser sajten (queries, indexeringsstatus, sitemap, position)

Använd kombinationen för datadrivna SEO/cache/UX-beslut istället för att gissa.

## Snabbreferens

|                               |                                                                                                   |
| ----------------------------- | ------------------------------------------------------------------------------------------------- |
| **GA4 property-ID**           | `305258979`                                                                                       |
| **GA4 property-namn**         | `http://brottsplatskartan.se - GA4`                                                               |
| **Measurement ID (frontend)** | `G-L1WVBJ39GH` (i `layouts/web.blade.php`)                                                        |
| **Search Console site_url**   | `https://brottsplatskartan.se/` (URL-prefix property, siteOwner)                                  |
| **GCP-projekt**               | `brottsplatskarta-1476012766238`                                                                  |
| **GA4 MCP-paket**             | [`analytics-mcp`](https://github.com/googleanalytics/google-analytics-mcp) (PyPI, experimentellt) |
| **GSC MCP-paket**             | [`mcp-gsc`](https://github.com/AminForou/mcp-gsc) (PyPI, v0.3.2 april 2026)                       |
| **Scope i Claude Code**       | `user` (per dator, inte i repo)                                                                   |

Property-ID är vad alla MCP-anrop tar som `property_id`. Measurement ID `G-…` är för JS-trackern på sajten — **inte** samma sak.

## Setup på ny dator

Båda MCP är registrerade per dator (Claude Code `--scope user`). Setup-guider:

- **GA4 (`analytics-mcp`):** [todos/done/08-ga-mcp.md](../todos/done/08-ga-mcp.md)
- **Search Console (`mcp-gsc`):** [todos/done/26-gsc-mcp.md](../todos/done/26-gsc-mcp.md)

Båda återanvänder samma GCP-projekt + OAuth-klient. Search Console kräver bara extra scope (`webmasters.readonly`) och separat API-aktivering.

Verifiera att de funkar:

```bash
claude mcp list | grep -E "analytics-mcp|mcp-gsc"   # båda ska visa "✓ Connected"
```

Om disconnected: troligen utgången OAuth-token. Kör om login-kommandot från respektive todo.

## MCP-verktyg som finns

### `analytics-mcp` (GA4)

Alla anropas som `mcp__analytics-mcp__<name>`:

| Tool                                | Användning                                                                 |
| ----------------------------------- | -------------------------------------------------------------------------- |
| `get_account_summaries`             | Lista alla konton + properties användaren har access till                  |
| `get_property_details`              | Metadata för en specifik property                                          |
| `get_custom_dimensions_and_metrics` | Egendefinierade dimensions/metrics på vår property (vi har inga särskilda) |
| `list_property_annotations`         | Annotations satta i GA4                                                    |
| `list_google_ads_links`             | Google Ads-kopplingar (vi har inga)                                        |
| `run_report`                        | **Huvudverktyget** — kör en GA4 Data API-rapport                           |
| `run_realtime_report`               | Realtidsrapport (senaste 30 min)                                           |

### `mcp-gsc` (Search Console)

Alla anropas som `mcp__mcp-gsc__<name>`. För brottsplatskartan: `site_url = "https://brottsplatskartan.se/"`.

| Tool                                | Användning                                                          |
| ----------------------------------- | ------------------------------------------------------------------- |
| `list_properties`                   | Lista alla properties användaren har access till                    |
| `get_site_details`                  | Metadata för en property                                            |
| `get_search_analytics`              | Enkel rapport — top queries/pages senaste 28 dagar                  |
| `get_advanced_search_analytics`     | **Huvudverktyget** — fullständig query med filter, sort, pagination |
| `inspect_url_enhanced`              | Indexeringsstatus + rich results för en URL                         |
| `batch_url_inspection`              | Inspektera flera URL:er samtidigt (rate-limited)                    |
| `check_indexing_issues`             | Bulk-kontroll av indexerings-/CWV-problem                           |
| `list_sitemaps_enhanced`            | Lista submitted sitemaps + status                                   |
| `submit_sitemap` / `delete_sitemap` | Hantera sitemap-registrering                                        |
| `get_sitemap_details`               | Per-sitemap: submitted vs indexed page-count                        |
| `get_performance_overview`          | Sammanfattning över hela property:n                                 |
| `compare_search_periods`            | Period-jämförelse                                                   |

## Exempel-queries

Verifierade anrop som har körts mot Brottsplatskartans property. Kopiera och justera datum/dimensioner.

### Top landningssidor från Google organisk, 30 dagar

```json
{
    "property_id": 305258979,
    "date_ranges": [{ "start_date": "30daysAgo", "end_date": "today" }],
    "dimensions": ["landingPage"],
    "metrics": [
        "sessions",
        "engagedSessions",
        "engagementRate",
        "averageSessionDuration"
    ],
    "dimension_filter": {
        "and_group": {
            "expressions": [
                {
                    "filter": {
                        "field_name": "sessionSource",
                        "string_filter": {
                            "value": "google",
                            "match_type": "EXACT"
                        }
                    }
                },
                {
                    "filter": {
                        "field_name": "sessionMedium",
                        "string_filter": {
                            "value": "organic",
                            "match_type": "EXACT"
                        }
                    }
                }
            ]
        }
    },
    "order_bys": [{ "metric": { "metric_name": "sessions" }, "desc": true }],
    "limit": 50
}
```

### Mobile vs desktop-trend för en route, 90 dagar

```json
{
    "property_id": 305258979,
    "date_ranges": [{ "start_date": "90daysAgo", "end_date": "today" }],
    "dimensions": ["date", "deviceCategory"],
    "metrics": ["sessions", "screenPageViews"],
    "dimension_filter": {
        "filter": {
            "field_name": "pagePath",
            "string_filter": {
                "value": "/handelser",
                "match_type": "BEGINS_WITH"
            }
        }
    },
    "order_bys": [
        { "dimension": { "dimension_name": "date" }, "desc": false },
        { "dimension": { "dimension_name": "deviceCategory" }, "desc": false }
    ],
    "limit": 300
}
```

`match_type` kan vara `EXACT`, `BEGINS_WITH`, `ENDS_WITH`, `CONTAINS`, `FULL_REGEXP`, `PARTIAL_REGEXP`.

### Trafikkällor över hela sajten, 30 dagar

```json
{
    "property_id": 305258979,
    "date_ranges": [{ "start_date": "30daysAgo", "end_date": "today" }],
    "dimensions": ["sessionDefaultChannelGroup", "sessionSource"],
    "metrics": ["sessions", "totalUsers"],
    "order_bys": [{ "metric": { "metric_name": "sessions" }, "desc": true }],
    "limit": 30
}
```

### Realtidstrafik just nu

```json
{
    "property_id": 305258979,
    "dimensions": ["unifiedScreenName"],
    "metrics": ["activeUsers"]
}
```

Använder `run_realtime_report` (inte `run_report`).

### GSC: Top sökfraser för en route-grupp

```json
{
    "site_url": "https://brottsplatskartan.se/",
    "dimensions": "query,page",
    "filter_dimension": "page",
    "filter_operator": "contains",
    "filter_expression": "/plats/",
    "sort_by": "clicks",
    "row_limit": 50
}
```

Använder `get_advanced_search_analytics`.

### GSC: Är en URL indexerad?

```json
{
    "site_url": "https://brottsplatskartan.se/",
    "page_url": "https://brottsplatskartan.se/plats/uppsala/handelser/25-april-2026"
}
```

Använder `inspect_url_enhanced`. För flera URL:er samtidigt: `batch_url_inspection` med `urls` som newline-separerad sträng.

### GSC: Submitta sitemap

```json
{
    "site_url": "https://brottsplatskartan.se/",
    "sitemap_url": "https://brottsplatskartan.se/sitemap.xml"
}
```

Använder `submit_sitemap`. Gjordes 2026-04-26 — sitemap.xml är nu inskickad.

## Tips för anrop

- **Dimensioner och metrics i `snake_case`** i protobuf-formatet, men i parameter-värden är fältnamnen `camelCase` (`sessionSource`, `landingPage`, `deviceCategory`). Det är förvirrande men följ docs på <https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema>.
- **Stora resultat sparas i fil** av Claude Code (>25 KB). Hantera med ett kort Python/jq-aggregat istället för att läsa råa raderna.
- **Datumformat:** `YYYY-MM-DD`, eller relativt `NdaysAgo`, `today`, `yesterday`.
- **GA4-kvota:** core tokens per property per dag. Vi är miles från taket — men kör inte stora unfiltered queries i loop.

## Vad vi vet om brottsplatskartan-trafiken

Konkreta insikter från 2026-04-26 (uppdatera när nya analyser görs):

- **Mobile dominerar:** ~80 % mobile, ~20 % desktop, <2 % tablet på `/handelser`-prefixet. Optimera CWV och OG-bilder för mobil först.
- **`/stockholm` är största enskilda landningssidan** från Google organisk: ~6 300 sessions/30d (8 493 om man räknar all trafik). Mer än startsidan (~4 600).
- **Län-sidor (`/lan/*`) dominerar topp-listor** — 11 av top 20 landningssidor från Google organisk är län. Cache pre-warm bör fokusera där.
- **Pageviews/session ~1.3** — användare landar, klickar in på _ett_ event, lämnar. Lågt djup. Listvyer som drar trafik bouncar i hög grad.
- **Indexerade pages: minst ~53 670** (proxy: unika URL:er med ≥1 impression senaste 30d). Stor långsvans — 4876 unika `/plats/*/handelser/{date}`-URL:er + 1677 `/lan/*/handelser/{date}` + 7632 single events.
- **Datum-URL:er rankar long-tail i Google (top 1-3)** trots att ingen söker proaktivt på datum. De får trafik på "[brott] [plats] [år]"-queries där datum är kontextuellt. Räddade oss från att 410:a dem (todo #1 sluthantering).
- **Stora städer rankar sämre än de borde** på `/plats/{stad}` (pos 7-10) — drev beslutet att ge dem dedikerade `/{stad}`-sidor (todo #24).
- **Sitemap inte submitted till GSC innan 2026-04-26** — Google upptäckte URL:er bara via interna länkar. Submission gjord, kommer förbättra crawl-effektivitet.
- **Case-duplikat:** `/plats/Malmö` + `/plats/malmö` rankades separat. Fottern länkade capitalized vilket spred problemet via crawl. Fixat i todo #23.
- **`(not set)`-rad i landingPage-rapporten** med 3 % engagement — bot-trafik eller borttagna sidor. Värt en separat undersökning.

Lägg till nya insikter här istället för att göra om analyserna i framtida sessioner.

## Säkerhet

- **`--scope user`, aldrig `project`.** `--scope project` skulle checka in en absolut sökväg till ADC-filen i `.mcp.json`. Filen ska aldrig hamna i repo.
- **Inga credentials i repo.** ADC-token ligger i `~/.config/gcloud/application_default_credentials.json` (chmod 600 default). Förekommer den i någon fil i repo: ta bort.
- **GA4-data passerar Anthropic.** Pär:s GA4 har inga inloggade användare och anonymiserar IP — men `page_path` kan innehålla sökfraser och liknande. Behandla transcripts som innehållande aggregerad besöksdata.
- **Scope:** OAuth-token har bara `analytics.readonly` + `cloud-platform`. Servern kan inte ändra något i GA4 ens om den ville.
- **Rotera vid borttappad dator:** `gcloud auth application-default revoke` och radera OAuth-klienten i [GCP Credentials](https://console.cloud.google.com/apis/credentials?project=brottsplatskarta-1476012766238).

## När analytics-mcp inte funkar

- `claude mcp list` säger disconnected → token utgången, kör om `gcloud auth application-default login`-kommandot från setup-todo:n.
- `403 access_denied` i webbläsaren vid login → ditt Googlekonto saknas i OAuth-skärmens _Test users_. Lägg till i [GCP consent screen](https://console.cloud.google.com/apis/credentials/consent?project=brottsplatskarta-1476012766238).
- `permission denied` på själva queries → kontot saknar GA4-property-access. Lägg till i GA4 Admin → Property access management.
- `Cannot find a quota project` warning → kör `gcloud auth application-default set-quota-project brottsplatskarta-1476012766238`.

## Referenser

### GA4

- analytics-mcp: <https://github.com/googleanalytics/google-analytics-mcp>
- GA4 Data API schema: <https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema>
- GA4 Data API quotas: <https://developers.google.com/analytics/devguides/reporting/data/v1/quotas>
- FilterExpression-syntax: <https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/FilterExpression>
- OrderBy-syntax: <https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/OrderBy>

### Search Console

- mcp-gsc: <https://github.com/AminForou/mcp-gsc>
- Search Console API: <https://developers.google.com/webmaster-tools/v1/api_reference_index>
- Search Analytics filter-syntax: <https://developers.google.com/webmaster-tools/v1/searchanalytics/query>
