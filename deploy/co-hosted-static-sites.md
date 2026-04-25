# Co-hostade statiska sajter

BPK-servern på Hetzner co-hostar några statiska sajter som ägs av samma
person men ligger i egna GitHub-repon. Den här dokumentationen förklarar
upplägget och hur man lägger till en ny.

## Arkitektur

```
GitHub repo (statisk sajt)            Hetzner-servern
    │                                       │
    │ git push main                         │
    │                                       │
    ▼                                       │
GitHub Actions ─── rsync över SSH ─────────►│   /opt/static-sites/<sajt>/
                                            │              │
                                            │              │ read-only mount
                                            │              ▼
                                            │   Caddy-container (BPK compose)
                                            │              │
                                            │              │ file_server
                                            │              ▼
                                            └──── https://<sajt>/  (TLS via Let's Encrypt)
```

- Innehållet bor kvar i sitt eget repo
- BPK-repot innehåller bara två konfig-rader per sajt (Caddyfile-block + compose-mount)
- Push till statiska repots `main` deployar automatiskt
- Caddy plockar upp filerna utan reload (read-only volume, file_server cachear inte)

## Initial server-setup (engångs, redan gjord 2026-XX-XX)

```bash
ssh deploy@brottsplatskartan.se
sudo mkdir -p /opt/static-sites
sudo chown deploy:deploy /opt/static-sites
```

Compose-mounten i `compose.yaml` (caddy-tjänsten) bind:ar in den read-only.

## Lägga till en ny statisk sajt

### 1. Skapa katalogen på servern

```bash
ssh deploy@brottsplatskartan.se 'mkdir -p /opt/static-sites/<dom.tld>'
```

### 2. Lägg till site-block i `deploy/Caddyfile`

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

Pusha → BPK-deploy startar om Caddy → ny domän börjar prova ACME mot Let's Encrypt.

### 3. Sätt upp GitHub Action i den statiska sajtens repo

Skapa `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Hetzner

on:
    push:
        branches: [main]
    workflow_dispatch:

jobs:
    deploy:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v4

            - name: Setup SSH
              run: |
                  mkdir -p ~/.ssh
                  echo "${{ secrets.HETZNER_SSH_KEY }}" > ~/.ssh/id_ed25519
                  chmod 600 ~/.ssh/id_ed25519
                  ssh-keyscan -H brottsplatskartan.se >> ~/.ssh/known_hosts

            - name: Rsync public_html to server
              run: |
                  rsync -avz --delete \
                      public_html/ \
                      deploy@brottsplatskartan.se:/opt/static-sites/<dom.tld>/
```

Lägg till repo-secret `HETZNER_SSH_KEY` (samma deploy-nyckel som BPK
använder, eller separat — se nedan).

### 4. DNS

Hos Loopia (eller där domänen ligger): peka A-record (apex + www) på
Hetzner-IP:n för BPK-servern. Caddy fixar TLS automatiskt vid första
träff.

## Säkerhet

### SSH-nyckel-strategi

Du kan välja:

- **Återanvänd BPK:s deploy-nyckel** — enklast, samma nyckel funkar för
  alla repon
- **Separata deploy-nycklar per sajt** — striktare blast radius om en
  GitHub-org skulle komprometteras. Läggs som `~/.ssh/authorized_keys`
  rad på `deploy@brottsplatskartan.se` med `command="rsync …"` för att
  begränsa till bara den ena katalogen

För dessa två sajter (antonblomqvist.se, simple-fields.com) räcker
återanvändning — låg risk, samma ägare.

### Rsync-katalog-isolering

GitHub Actions behöver bara skrivrättigheter till `/opt/static-sites/<sajt>/`.
För hård isolering: använd `command=` i `authorized_keys` som låser
SSH-sessionen till just det rsync-anropet.

## Felsökning

### Sajten visar "Can't find a site"

- Kolla att A-record pekar på rätt IP: `dig <dom.tld>`
- Kolla att Caddy plockat upp config: `docker compose logs caddy | grep <dom.tld>`
- Kolla att filerna finns på servern: `ssh deploy@brottsplatskartan.se 'ls /opt/static-sites/<dom.tld>/'`

### TLS-cert misslyckas

Caddy försöker ACME automatiskt. Om DNS inte propagerat än får man
404 från Let's Encrypt. Vänta 5–10 min, kolla logg: `docker compose logs caddy`.

### Deploy-action failar med "Permission denied"

`HETZNER_SSH_KEY`-secret saknas eller är fel. Lägg in nyckeln på
servern: `ssh deploy@brottsplatskartan.se 'cat >> ~/.ssh/authorized_keys' < deploy-key.pub`.
