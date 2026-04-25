**Status:** aktiv — kod + deploy klart, väntar på DNS-byte
**Senast uppdaterad:** 2026-04-25

# Todo #21 — Migrera antonblomqvist.se + simple-fields.com till BPK Hetzner

## Sammanfattning

Två statiska sajter som tidigare körts på gamla DO-Dokku-servern
(`dokku.eskapism.se`, IP `138.68.89.224`) ska flyttas till BPK:s
Hetzner-server och co-hostas där. Infrastrukturen för co-hostning är
redan på plats i BPK-repot (commit `e272654`), nu återstår bara
sajt-specifika steg.

## Bekräftat statiska

Inventering 2026-04-25:

| Sajt              | Repo                                                                    | Storlek | Innehåll                                                                             | Default branch |
| ----------------- | ----------------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------ | -------------- |
| antonblomqvist.se | [`bonny/antonblomqvist.se`](https://github.com/bonny/antonblomqvist.se) | 63 KB   | `public_html/` med `index.html`, `bajs.jpg`, `images/`                               | `master`       |
| simple-fields.com | [`bonny/simple-fields.com`](https://github.com/bonny/simple-fields.com) | 2.6 MB  | `public_html/` med årsarkiv 2012–2017, about/, api/, author/ etc. (gammal WP-export) | `master`       |

Båda har bara: `public_html/`, `static.json` (heroku-buildpack-static),
`.buildpacks`, README. Senast pushade 2020-06-04 — frusna sedan ~6 år.

## Förutsättning

- ✅ BPK-server har co-hostning-infrastruktur (`/opt/static-sites/`,
  `/opt/caddy-sites.d/`, Caddyfile-import) — commit `e272654`
- ✅ Backup tagen av gamla DO-data (todo #14)

## Migreringssteg

### 1. Initial server-setup (en gång, för båda sajterna)

```bash
ssh deploy@brottsplatskartan.se '
    sudo mkdir -p /opt/static-sites /opt/caddy-sites.d
    sudo chown -R deploy:deploy /opt/static-sites /opt/caddy-sites.d
'
```

Verifiera att compose-mountarna fungerar:

```bash
ssh deploy@brottsplatskartan.se 'cd /opt/brottsplatskartan && docker compose exec caddy ls /etc/caddy/sites.d /srv/static-sites'
```

### 2. Per sajt — gör i varje statisk sajts repo

Workflow är dokumenterad i `deploy/co-hosted-static-sites.md`. Kortversion:

#### a. Skapa `caddy/<dom.tld>.caddy` i sajt-repot

```caddy
www.<dom.tld> {
    redir https://<dom.tld>{uri} permanent
}

<dom.tld> {
    root * /srv/static-sites/<dom.tld>
    encode gzip zstd
    file_server
}
```

#### b. Skapa `.github/workflows/deploy.yml` i sajt-repot

⚠️ Båda repona har default branch `master`, inte `main`. Använd
`branches: [master]` i workflow-triggern.

(Se mall i `deploy/co-hosted-static-sites.md` § Lägga till en ny
co-hostad sajt — ändra bara `branches` och `SITE`-env-var.)

#### c. Lägg till repo-secret `HETZNER_SSH_KEY`

Samma SSH-deploy-nyckel som BPK använder, eller separat (se säkerhets-
sektionen i `co-hosted-static-sites.md`). Lägg in den publika nyckeln
i `~/.ssh/authorized_keys` för `deploy`-användaren på Hetzner-servern
om separat används.

#### d. Pusha till `master` → GHA deployar

Verifiera:

```bash
ssh deploy@brottsplatskartan.se '
    ls /opt/static-sites/<dom.tld>/
    cat /opt/caddy-sites.d/<dom.tld>.caddy
'
```

#### e. DNS-byte hos Loopia

Peka A-records för apex + www mot Hetzner-IP:n. Vänta på TLS-cert
från Let's Encrypt (Caddy försöker automatiskt).

```bash
dig +short antonblomqvist.se     # ska visa Hetzner-IP, inte 138.68.89.224
curl -I https://antonblomqvist.se # ska ge 200 OK med valid cert
```

## Risker & småsaker

- **Default branch `master`:** kom ihåg att uppdatera workflow-triggern
- **simple-fields.com `_downloads.html`** — ev. legacy-hänvisningar utåt
  som länkar dit, värt att verifiera att filen kommer med
- **TLS-utgivning:** Caddy provar Let's Encrypt direkt vid första request.
  Om DNS inte propagerat misslyckas det första gången — Caddy försöker
  igen automatiskt
- **Bandbredd:** simple-fields.com har 2.6 MB innehåll, ingen risk för
  Hetzner-bandbreddstak

## Status / nästa steg

- [x] Skapa `/opt/static-sites/` + `/opt/caddy-sites.d/` på servern (2026-04-25)
- [x] simple-fields.com: byt master→main, lägg till `caddy/*.caddy` + GHA, push (2026-04-25)
- [x] antonblomqvist.se: byt master→main, lägg till `caddy/*.caddy` + GHA, push (2026-04-25)
- [x] `HETZNER_SSH_KEY` repo-secret satt i båda repona (2026-04-25, samma deploy-nyckel som BPK)
- [x] Workflows kör framgångsrikt — `public_html/` rsync:ad till `/opt/static-sites/<sajt>/`, `<sajt>.caddy` till `/opt/caddy-sites.d/`, Caddy reloadat (2026-04-25)
- [ ] DNS hos Loopia: peka apex + www för båda mot Hetzner-IP `62.238.25.254` (A) + `2a01:4f9:c014:a8b1::1` (AAAA)
- [ ] Verifiera TLS-cert utfärdas + sajterna svarar med 200 OK
- [ ] Stäng todo, signalera att #16 kan gå vidare med radering av DO-droppleten
