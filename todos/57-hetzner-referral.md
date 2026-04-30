**Status:** aktiv (idé — research saknas)
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #57 — Aktivera Hetzners referral-program

## Sammanfattning

> Använda/aktivera hetzner referral program.

Vi kör hela produktionsmiljön på Hetzner (CX33, EU/Helsinki) sedan
migrationen från DO. Referral-länken ger Hetzner-credit till oss och
välkomstbonus till nya kunder via vår länk.

## Bakgrund

Hetzner har ett officiellt referral-program: en personlig referral-
URL som ger den nya kunden €20 cloud-credit, och oss €10 i credit
per kund som faktiskt genererar minst €10 förbrukning under första
månaden (kolla aktuella siffror på robot/cloud-portalen — kan ha
ändrats). Linkar och dashboard finns under "Account" i Hetzner Console.

Naturlig synergi med vår "Hosted in EU"-kommunikation (#13 klar
2026-04-24) — vi kan länka till Hetzner i `/sida/om` med "Här är
varför vi valde Hetzner" + referral-URL.

## Förslag

1. **Hämta referral-URL** från Hetzner Console (Account →
   Refer-a-Friend eller liknande).
2. **Lägg till länk på `/sida/om`:** ett kort stycke "Vi kör på
   Hetzner i Helsingfors. Vill du också? <referral-länk>".
3. **Footer-länk valbar** — beroende på hur intrusiv vi vill vara.
   Sannolikt skippa, om-sidan räcker.
4. **Spårning:** Hetzner rapporterar conversions i deras dashboard.
   Inget lokalt analytics behövs initialt.

## Risker

- **Referral-länk kan tolkas som affiliate-spam** → påverka E-E-A-T-
  signaler. Mitigera genom att vara transparent ("Vi får credit om
  du registrerar dig via länken nedan, men vi rekommenderar dem oavsett.").
- **Länken kan rotera/upphöra** om Hetzner ändrar villkor — sätt en
  påminnelse 1× per år att kolla att den lever.

## Confidence

**Hög.** Trivial implementation. Värdet är osäkert (vi har låg trafik
till om-sidan) men noll-kost.

## Beroenden

- Bygger på #13 ("Hosted in EU"-kommunikation).

## Nästa steg

1. Hämta referral-URL från Hetzner Console.
2. Skriv 2–3 meningar text till `/sida/om` (transparent disclosure).
3. Deploy. Mät conversions efter 90d — om < 1, fundera om den
   ska tas bort eller flyttas.
