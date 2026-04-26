# Analytics-data (GA4)

Hur Claude (och människor) kommer åt Brottsplatskartans Google Analytics-data via `analytics-mcp` MCP-server. Använd detta för datadrivna SEO/cache/UX-beslut istället för att gissa.

## Snabbreferens

|                               |                                                                                                   |
| ----------------------------- | ------------------------------------------------------------------------------------------------- |
| **GA4 property-ID**           | `305258979`                                                                                       |
| **GA4 property-namn**         | `http://brottsplatskartan.se - GA4`                                                               |
| **GA4-konto**                 | `Pär privat` (181460)                                                                             |
| **Measurement ID (frontend)** | `G-L1WVBJ39GH` (i `layouts/web.blade.php`)                                                        |
| **GCP-projekt**               | `brottsplatskarta-1476012766238`                                                                  |
| **MCP-paket**                 | [`analytics-mcp`](https://github.com/googleanalytics/google-analytics-mcp) (PyPI, experimentellt) |
| **Scope i Claude Code**       | `user` (per dator, inte i repo)                                                                   |

Property-ID är vad alla MCP-anrop tar som `property_id`. Measurement ID `G-…` är för JS-trackern på sajten — **inte** samma sak.

## Setup på ny dator

`analytics-mcp` är registrerad per dator (Claude Code `--scope user`). På en ny dator: följ [todos/done/08-ga-mcp.md](../todos/done/08-ga-mcp.md) — alla install-steg + OAuth-flow ligger där.

Verifiera att det funkar:

```bash
claude mcp list | grep analytics-mcp   # ska visa "✓ Connected"
```

Om disconnected: troligen utgången OAuth-token. Kör om login-kommandot från todo:n.

## MCP-verktyg som finns

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

## Tips för anrop

- **Dimensioner och metrics i `snake_case`** i protobuf-formatet, men i parameter-värden är fältnamnen `camelCase` (`sessionSource`, `landingPage`, `deviceCategory`). Det är förvirrande men följ docs på <https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema>.
- **Stora resultat sparas i fil** av Claude Code (>25 KB). Hantera med ett kort Python/jq-aggregat istället för att läsa råa raderna.
- **Datumformat:** `YYYY-MM-DD`, eller relativt `NdaysAgo`, `today`, `yesterday`.
- **GA4-kvota:** core tokens per property per dag. Vi är miles från taket — men kör inte stora unfiltered queries i loop.

## Vad vi vet om brottsplatskartan-trafiken

Konkreta insikter från 2026-04-26 (uppdatera när nya analyser görs):

- **Mobile dominerar:** ~80 % mobile, ~20 % desktop, <2 % tablet på `/handelser`-prefixet. Optimera CWV och OG-bilder för mobil först.
- **`/stockholm` är största enskilda landningssidan** från Google organisk: ~6 300 sessions/30d. Mer än startsidan (~4 600).
- **Län-sidor (`/lan/*`) dominerar topp-listor** — 11 av top 20 landningssidor från Google organisk är län. Cache pre-warm bör fokusera där.
- **`/handelser/{date}`-mönstret syns inte i top landningssidor** — bekräftar att datum-paginering kan slopas/noindexas utan SEO-tapp (relevant för todo #1 + #11 P1 punkt 6).
- **Pageviews/session ~1.3** — användare landar, klickar in på _ett_ event, lämnar. Lågt djup. Listvyer som drar trafik bouncar i hög grad.
- **`(not set)` på plats 7** i top landningssidor med 3 % engagement — bot-trafik eller borttagna sidor. Värt en separat undersökning.

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

- analytics-mcp: <https://github.com/googleanalytics/google-analytics-mcp>
- GA4 Data API schema: <https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema>
- GA4 Data API quotas: <https://developers.google.com/analytics/devguides/reporting/data/v1/quotas>
- FilterExpression-syntax: <https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/FilterExpression>
- OrderBy-syntax: <https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/OrderBy>
