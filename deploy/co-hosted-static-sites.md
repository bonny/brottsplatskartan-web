# Co-hostade externa sajter

BPK-servern på Hetzner kan co-hosta statiska sajter som ägs av samma
person men ligger i egna GitHub-repon. Det här dokumentet beskriver
arkitekturen.

**Viktig egenskap:** BPK-repot vet **inte** vilka sajter som co-hostas.
Varje sajt levererar sitt eget Caddy-block och sitt eget innehåll från
sitt eget repo. Att lägga till eller ta bort en co-hostad sajt kräver
ingen ändring i BPK-repot.

## Arkitektur

```
GitHub repo (sajt X)                       Hetzner-servern
    │                                            │
    │ git push main                              │
    │                                            │
    ▼                                            │
GitHub Actions ─── rsync över SSH ─────────────► │   /opt/static-sites/<sajt>/
                              ─── rsync ───────► │   /opt/caddy-sites.d/<sajt>.caddy
                              ─── caddy reload ► │              │
                                                 │              │ (read-only mounts)
                                                 │              ▼
                                                 │   Caddy-container (BPK compose)
                                                 │   `import /etc/caddy/sites.d/*.caddy`
                                                 │              │
                                                 │              ▼
                                                 └── https://<sajt>/ (TLS via Let's Encrypt)
```

**Vad BPK-repot bidrar med:**

- En `import /etc/caddy/sites.d/*.caddy` rad i `deploy/Caddyfile`
- Två bind-mounts i `compose.yaml` (`/opt/static-sites` och
  `/opt/caddy-sites.d`)

Inget mer. Inga sajt-namn nämns i BPK.

## Initial server-setup (engångs)

```bash
ssh deploy@brottsplatskartan.se '
    sudo mkdir -p /opt/static-sites /opt/caddy-sites.d
    sudo chown -R deploy:deploy /opt/static-sites /opt/caddy-sites.d
'
```

Mounten i BPK:s `compose.yaml` (caddy-tjänsten) bind:ar in dem read-only.

## Lägga till en ny co-hostad sajt

Allt görs i den **statiska sajtens eget repo**. Inget i BPK-repot.

### 1. Skapa `caddy/<dom.tld>.caddy` i sajtens repo

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

### 2. Skapa `.github/workflows/deploy.yml` i sajtens repo

```yaml
name: Deploy to Hetzner

on:
    push:
        branches: [main]
    workflow_dispatch:

jobs:
    deploy:
        runs-on: ubuntu-latest
        env:
            SITE: <dom.tld>
        steps:
            - uses: actions/checkout@v4

            - name: Setup SSH
              run: |
                  mkdir -p ~/.ssh
                  echo "${{ secrets.HETZNER_SSH_KEY }}" > ~/.ssh/id_ed25519
                  chmod 600 ~/.ssh/id_ed25519
                  ssh-keyscan -H brottsplatskartan.se >> ~/.ssh/known_hosts

            - name: Sync content
              run: |
                  rsync -avz --delete \
                      public_html/ \
                      deploy@brottsplatskartan.se:/opt/static-sites/$SITE/

            - name: Sync Caddy snippet
              run: |
                  rsync -avz \
                      caddy/$SITE.caddy \
                      deploy@brottsplatskartan.se:/opt/caddy-sites.d/$SITE.caddy

            - name: Reload Caddy
              run: |
                  ssh deploy@brottsplatskartan.se '
                      cd /opt/brottsplatskartan
                      docker compose exec -T caddy \
                          caddy reload --config /etc/caddy/Caddyfile
                  '
```

Lägg till repo-secret `HETZNER_SSH_KEY` (samma deploy-nyckel som BPK
eller en separat — se Säkerhet nedan).

### 3. DNS

Hos Loopia (eller där domänen ligger): peka apex + www mot
Hetzner-IP:n för BPK-servern. Caddy fixar TLS automatiskt vid första
träff.

### 4. Pusha

`git push origin main` triggar deploy. GitHub Action skickar
innehållet, snippet:en och triggar Caddy-reload.

## Ta bort en co-hostad sajt

På servern:

```bash
ssh deploy@brottsplatskartan.se '
    rm /opt/caddy-sites.d/<dom.tld>.caddy
    rm -rf /opt/static-sites/<dom.tld>
    cd /opt/brottsplatskartan
    docker compose exec -T caddy caddy reload --config /etc/caddy/Caddyfile
'
```

DNS:en hos Loopia kan peka om eller raderas separat. Inget i BPK-repot
behöver röras.

## Säkerhet

### SSH-nyckel-strategi

För minimerad blast radius — separat deploy-nyckel per sajt, med
`command="…"` i `~/.ssh/authorized_keys` som låser sessionen till de
rsync- och caddy-reload-anrop som workflow:n behöver.

För dessa två sajter (antonblomqvist.se, simple-fields.com) räcker
återanvändning av BPK:s deploy-nyckel — låg risk, samma ägare.

### Ingen sajt kan kapa en annan

Caddy-snippet:en pekar på `/srv/static-sites/<dom.tld>` med samma
sajt-namn som filen — så ingen kan via en pull request ändra var
content hämtas från.

## Felsökning

### Sajten visar "Can't find a site"

- Kolla att A-record pekar rätt: `dig <dom.tld>`
- Kolla att Caddy plockat upp config: `docker compose logs caddy | grep <dom.tld>`
- Kolla snippet och innehåll på servern:
  `ssh deploy@brottsplatskartan.se 'ls /opt/caddy-sites.d/ /opt/static-sites/'`

### Caddy reload misslyckas

Om snippet:en har syntaxfel refuserar Caddy ny config men fortsätter
köra den gamla — så live-trafik bryts inte. Kolla
`docker compose logs caddy` för felmeddelandet.

### TLS-cert misslyckas

Om DNS inte propagerat får man ACME-fel. Vänta 5–10 min, kolla logg:
`docker compose logs caddy | grep -i acme`.
