**Status:** klar 2026-04-26
**Senast uppdaterad:** 2026-04-26

# Todo #26 — Google Search Console MCP-server

Komplement till #8 (GA4 MCP). Search Console-data är _mer_ direkt
SEO-relevant än GA4: visar exakta queries, indexeringsstatus, sitemap-
fångst. Kombinationen GA4+GSC svarar på "vad händer på sajten" och "hur
ser Google sajten" tillsammans.

## Utfört

- Verktygsval: **AminForou/mcp-gsc** (730 stars, v0.3.2 april 2026,
  aktivt underhållen). Body står emot ahonn/mcp-server-gsc (211 stars).
- Installerad lokalt via `brew install uv` (Astral.sh — ny Python-
  pakethanterare, motsvarande pipx).
- GCP-projekt återanvänt från GA4 (`brottsplatskarta-1476012766238`).
  Search Console API aktiverat via webbläsare.
- OAuth-klient återanvänd (`~/.config/gcloud/brottsplatskartan-oauth-client.json`)
  — samma desktop-klient hanterar både GA4 och GSC.
- Registrerad i Claude Code med `--scope user` (per dator):
    ```bash
    claude mcp add mcp-gsc --scope user --transport stdio \
      --env GSC_OAUTH_CLIENT_SECRETS_FILE=$HOME/.config/gcloud/brottsplatskartan-oauth-client.json \
      -- uvx mcp-gsc
    ```
- Browser-OAuth-flow första gången (godkände `webmasters.readonly`).
- Verifierat: `list_properties` returnerar 14 properties; brottsplatskartan
  som `https://brottsplatskartan.se/` (siteOwner).
- **Sitemap submitted** till GSC samma session — det visade sig att
  vår sitemap (genererad dagligen) aldrig hade skickats in.
  `submit_sitemap` triggade — Google börjar processa.

## Datadrivna beslut den här MCP:n redan har möjliggjort

- **Todo #1 (cache-exkludering):** GSC visade att 6 553 unika datum-
  URL:er rankar (många top 1-3) — räddade oss från att 410:a dem.
- **Todo #24 (Tier 1-städer):** GSC visade att stora städer rankar
  pos 7-10 på "polisen händelser X"-queries trots hög impressions-
  volym → motiverade flytten till dedikerade `/<stad>`-sidor.
- **Indexed pages-räkning:** ~53 670 unika URL:er har impressions/30d
  (proxy för indexerade pages). Tidigare okänt.

## Setup på ny dator

1. `brew install uv` (om saknas)
2. Aktivera Search Console API: <https://console.cloud.google.com/apis/library/searchconsole.googleapis.com?project=brottsplatskarta-1476012766238>
3. Återanvänd OAuth-klient från [todos/done/08-ga-mcp.md](08-ga-mcp.md) eller skapa ny Desktop-klient i GCP.
4. Lägg till ditt Googlekonto som siteOwner/user i Search Console för brottsplatskartan-property:n.
5. `claude mcp add mcp-gsc --scope user --transport stdio --env GSC_OAUTH_CLIENT_SECRETS_FILE=$HOME/.config/gcloud/brottsplatskartan-oauth-client.json -- uvx mcp-gsc`
6. Starta om Claude Code, kör `list_properties` — browsern öppnas, godkänn `webmasters.readonly`.

## Tillgängliga tools

20 totalt. Mest använda hittills:

- `list_properties` — verifiera setup
- `inspect_url_enhanced` / `batch_url_inspection` — indexeringsstatus per URL
- `get_advanced_search_analytics` — top queries/sidor med page-filter
- `submit_sitemap` / `list_sitemaps_enhanced` — sitemap-hantering
- `check_indexing_issues` — bulk-kontroll av problem

Detaljer + exempel: [docs/analytics.md](../../docs/analytics.md).

## Säkerhet

Samma som #8 — `--scope user` (aldrig `project`), credentials i
`~/.config/gcloud/`, scope begränsat till `webmasters.readonly`.
GSC-data passerar Anthropic.

## Referenser

- mcp-gsc: <https://github.com/AminForou/mcp-gsc>
- Search Console API: <https://developers.google.com/webmaster-tools/v1/api_reference_index>
