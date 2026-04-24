**Status:** klar 2026-04-24
**Senast uppdaterad:** 2026-04-24

# Todo #17 — Ta bort `hetzner.*`-testdomänerna

## Utfört

- DNS-record för `hetzner.brottsplatskartan.se` och `hetzner-kartbilder.brottsplatskartan.se` borttagna i Loopia
- Caddy-blocken borttagna ur `deploy/Caddyfile`
- Deployat → `docker compose restart caddy` kör automatiskt via `deploy.sh`

## Sammanfattning

Under flytten från DO till Hetzner användes `hetzner.brottsplatskartan.se`
och `hetzner-kartbilder.brottsplatskartan.se` som test-endpoints mot nya
servern innan DNS-cutover. Cutover är klar (2026-04-22) och apex +
`kartbilder.` pekar nu på Hetzner. Test-domänerna är redundanta och ska bort.

## Steg

1. **Caddyfile:** ta bort blocken för `hetzner.brottsplatskartan.se`
   och `hetzner-kartbilder.brottsplatskartan.se` i `deploy/Caddyfile`
2. **DNS (Loopia):** ta bort A/AAAA/CNAME-records för `hetzner` och
   `hetzner-kartbilder`
3. **Deploy:** push → GHA → `docker compose restart caddy`
4. **Verifiera:** `curl -I https://hetzner.brottsplatskartan.se` ska
   inte längre svara (DNS NXDOMAIN eller Caddy default-reject)

## Risker

- Minimala. Ingen produktionstrafik går via dessa domäner. Let's Encrypt-
  certen för dem slutar förnyas automatiskt när Caddy-blocken är borta.

## När

Kan göras direkt — inget att vänta på.
