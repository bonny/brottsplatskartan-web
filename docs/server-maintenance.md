# Serverunderhåll

## 2026-02-12: Diskrensning och Docker-underhåll

Servern låg på **78% diskutnyttjande**. Efter städning: **44% (85 GB ledigt)**.

### Docker-rensning (~54 GB frigjort)

```bash
# Steg 1: Ta bort oanvända images och build cache (14 GB)
docker system prune -a

# Steg 2: Ta bort oanvända volymer (40 GB)
# Obs: "docker volume prune" utan --all tar bara bort anonyma volymer.
docker volume prune --all
```

**Tips:** `docker system df` visar vad som tar plats och hur mycket som kan rensas.

### Ambassador-container i restart loop

Dokku-ambassador-containern för MariaDB låg i en oändlig restart loop med felet:
> Failed to autodetect target host/container and port using --link environment.

Lösning — containern behövdes inte (appen ansluter via Dokku-intern Docker-networking):

```bash
dokku mariadb:unexpose mariadb-brottsplatskartan
```

### Resultat

| Före | Efter |
|------|-------|
| 78% diskutnyttjande | 44% diskutnyttjande |
| 8 containers (1 i restart loop) | 7 containers (alla friska) |
| 22 volymer (20 oanvända) | 2 volymer |
