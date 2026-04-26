# tmp-cwv

Lokal dump-mapp för Core Web Vitals-mätningar.

**Gitignored** — filerna ska inte committas. De behålls här så länge
disk räcker så vi kan jämföra mot framtida mätningar.

## Innehåll

`*_mobile.json` — Lighthouse CLI-rapporter (mobile, simulated throttling).
Filenames är derivable från URL-paths (sed-konverterade).

## Senaste mätning

Se "CWV-baseline"-sektionen i [todos/11-seo-audit-2026.md](../todos/11-seo-audit-2026.md).

## Köra om mätningar

```bash
TOKEN=$(gcloud auth application-default print-access-token)
mkdir -p tmp-cwv && cd tmp-cwv
for url in "https://brottsplatskartan.se/stockholm" "https://brottsplatskartan.se/" ... ; do
  fname=$(echo "$url" | sed 's|https://brottsplatskartan.se||g; s|[^a-zA-Z0-9]|_|g; s|^_||')
  [ -z "$fname" ] && fname=root
  npx --yes lighthouse "$url" \
    --only-categories=performance \
    --form-factor=mobile \
    --throttling-method=simulate \
    --output=json --output-path="${fname}_mobile.json" \
    --chrome-flags="--headless=new --no-sandbox" \
    --quiet
done
```

PageSpeed Insights API kräver API-key — Lighthouse CLI lokalt funkar
utan men ger samma simulerade data.

För **CRUX (real-user)** krävs PSI eller CRUX REST API + API-key.
