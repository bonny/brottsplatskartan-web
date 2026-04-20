# Provisionering av Hetzner-server

Körs en gång när den nya servern skapas. Antar **Debian 13 Trixie x86_64**.

Stacken är multi-arch så både CX (AMD x86) och CAX (ARM Ampere) fungerar
utan kodändringar. Nuvarande prod kör CX33 i Helsinki.

## 1. Skapa server i Hetzner Cloud Console

- Location: **Helsinki (HEL1)** (alternativ: Falkenstein/Nuremberg — alla EU)
- Type: **CX33** (x86 AMD, 4 vCPU / 8 GB / 80 GB, ~€6.49/mån)
  - Uppgradera senare till CX43 vid behov (kräver ~3 min downtime)
  - CAX-serien (ARM) fungerar också om du föredrar det
- Image: **Debian 13** (Trixie)
- SSH key: lägg till din publika nyckel (`~/.ssh/id_ed25519.pub`)
- Backups: **aktivera** (+20% kostnad)
- Networking: IPv4 + IPv6
- Firewall: skapa en basic firewall (SSH 22, HTTP 80, HTTPS 443)

## 2. Första SSH-in

```bash
ssh root@<hetzner-ip>
```

## 3. Sätt hostname + härda + installera basics

```bash
# Sätt hostname (byt ut vid behov)
hostnamectl set-hostname bpk-prod-hel1

# Hetzner cloud-init skriver om /etc/hosts vid boot per default.
# Stäng av det så vår ändring persisterar:
cat > /etc/cloud/cloud.cfg.d/99-disable-hosts.cfg <<'EOF'
manage_etc_hosts: false
EOF

# Uppdatera /etc/hosts så sudo inte klagar
sed -i "s/127.0.1.1 .*/127.0.1.1 $(hostname)/" /etc/hosts

# Uppdatera allt
apt update && apt upgrade -y

# Installera grundverktyg
apt install -y \
  ufw \
  fail2ban \
  unattended-upgrades \
  curl \
  ca-certificates \
  gnupg \
  git \
  htop \
  ncdu

# Auto-uppdateringar
dpkg-reconfigure -plow unattended-upgrades

# UFW
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Tidzon + NTP
timedatectl set-timezone Europe/Stockholm
```

## 4. Skapa deploy-user

```bash
adduser --disabled-password --gecos "" deploy
usermod -aG sudo deploy

# SSH-access (använd din publika nyckel här)
mkdir -p /home/deploy/.ssh
cp /root/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Passwordless sudo för deploy-skript (valfritt men praktiskt)
echo "deploy ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/deploy
```

## 5. Stäng av root-login över SSH

```bash
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart ssh
```

## 6. Installera Docker

Officiell Docker-install på Debian:

```bash
# Docker CE repo
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo $VERSION_CODENAME) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# deploy-user ska kunna köra docker utan sudo
usermod -aG docker deploy

# Auto-städning av gamla images (kör söndagar 03:00)
cat > /etc/cron.d/docker-prune <<'EOF'
0 3 * * 0 root docker system prune -af --filter "until=168h" > /dev/null 2>&1
EOF
```

## 7. Klona repot som deploy-user

```bash
su - deploy
sudo mkdir -p /opt/brottsplatskartan
sudo chown deploy:deploy /opt/brottsplatskartan

# Klona
git clone https://github.com/bonny/brottsplatskartan-web.git /opt/brottsplatskartan
cd /opt/brottsplatskartan
git checkout flytt-till-hetzner  # Byts till main efter cutover
```

## 8. Sätt upp .env

```bash
cp deploy/.env.example .env
chmod 600 .env
nano .env
# Fyll i alla värden (API-nycklar, genererade lösenord, APP_KEY)
```

Generera `APP_KEY`:
```bash
docker run --rm -v $PWD:/app serversideup/php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32));"
```

## 9. Ladda ner mbtiles (engångsvis, ~1.2 GB)

```bash
./deploy/download-tiles.sh
```

Scriptet är idempotent och hoppar över om filen redan finns. Tileserver-gl auto-detekterar mbtiles i `/data` och serverar dem med default-config — inget `config.json` behövs.

## 10. Starta stacken

```bash
# Första körningen bygger också app-imagen (tar ~30 sek pga install-php-extensions)
docker compose up -d --build
docker compose logs -f
```

## 10b. Installera composer-dependencies (engångsvis)

**Gotcha:** named volume `vendor/` ägs av root initialt, och AUTORUN
kör `storage:link` som kräver `vendor/`. Därför måste första composer
install:en köras som root med AUTORUN avstängt:

```bash
docker compose run --rm --no-deps -u root -e AUTORUN_ENABLED=false app \
  sh -c 'composer install --no-dev --optimize-autoloader && chown -R www-data:www-data /var/www/html/vendor /var/www/html/bootstrap/cache /var/www/html/storage'
```

Senare composer install (vid `composer.lock`-ändringar) görs av
`deploy/deploy.sh` som också hanterar perms.

## 11. Importera DB-dump från DO

```bash
# På DO:
ssh root@brottsplatskartan.se 'dokku mariadb:export mariadb-brottsplatskartan' > /tmp/bpk.dump.gz
scp /tmp/bpk.dump.gz deploy@<hetzner-ip>:/tmp/

# På Hetzner:
zcat /tmp/bpk.dump.gz | docker compose exec -T mariadb mysql -u root -p"$DB_ROOT_PASSWORD" brottsplatskartan
```

## 12. Dagliga DB-dumps (backup-lager 2)

Hetzner gör dagliga disk-snapshots (aktiverat i steg 1), men en
lokal SQL-dump i klartext är snabbare att inspektera och återställa
från vid misstag (råkat köra fel DELETE etc.).

```bash
sudo mkdir -p /opt/backups
sudo chown deploy:deploy /opt/backups

Skapa ett backup-script (följer MariaDB:s officiella Docker-doc —
lösenordet expanderas *inuti* containern via `$MARIADB_ROOT_PASSWORD`,
syns aldrig i host:ens process-listning):

```bash
sudo tee /usr/local/bin/bpk-db-backup.sh > /dev/null <<'EOF'
#!/bin/bash
set -euo pipefail
cd /opt/brottsplatskartan
OUT="/opt/backups/bpk-$(date +%F).sql.gz"
docker compose exec -T mariadb sh -c '
  exec mariadb-dump \
    --single-transaction \
    --routines --triggers --events \
    --databases brottsplatskartan \
    -u root -p"$MARIADB_ROOT_PASSWORD"
' | gzip > "$OUT"
find /opt/backups -name 'bpk-*.sql.gz' -mtime +7 -delete
EOF
sudo chmod +x /usr/local/bin/bpk-db-backup.sh

echo "0 4 * * * deploy /usr/local/bin/bpk-db-backup.sh >> /var/log/bpk-db-backup.log 2>&1" | \
  sudo tee /etc/cron.d/bpk-db-backup > /dev/null
sudo chmod 644 /etc/cron.d/bpk-db-backup
```

- `--single-transaction` = konsistent snapshot av InnoDB utan table locks
  (läsningar och skrivningar fortsätter obehindrat under dumpen).
- `--routines --triggers --events` = komplett dump av stored procedures,
  triggers och scheduled events.
- `--databases brottsplatskartan` = inkluderar `CREATE DATABASE`-statements,
  så restore blir idempotent (skapar DB:n om den inte finns).

Dumpar klockan 04:00 varje natt, behåller 7 dagar. Testa direkt:

```bash
/usr/local/bin/bpk-db-backup.sh
ls -lh /opt/backups/
```

Återställning (från host):

```bash
cd /opt/brottsplatskartan
zcat /opt/backups/bpk-2026-04-20.sql.gz | \
  docker compose exec -T mariadb sh -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD"'
```

## 13. Scheduler

Ingen host-cron behövs. En dedikerad `scheduler`-container i
`compose.yaml` kör `php artisan schedule:work` vilket triggar
Laravel Scheduler varje minut internt.

Verifiera att den kör:
```bash
docker compose ps scheduler
docker compose logs -f scheduler
```

## 14. Test via hetzner.brottsplatskartan.se

- Lägg A-record `hetzner.brottsplatskartan.se → <hetzner-ip>` i Loopia (TTL 300 s)
- Samma för `hetzner-kartbilder.brottsplatskartan.se`
- Vänta på DNS-propagering (~1 min med TTL 300 s)
- Caddy utfärdar automatiskt Let's Encrypt-cert
- Verifiera sajten

## 15. GitHub Actions secrets

I repots settings → Secrets:

- `HETZNER_HOST` = servers publika IP eller domän
- `HETZNER_SSH_KEY` = deploy-userns privata nyckel (generera på servern, lägg till pubkey i `~/.ssh/authorized_keys`)

## 16. Cutover (när allt är testat)

Se migrationsplanen i huvuddokumentet (Fas 6).
