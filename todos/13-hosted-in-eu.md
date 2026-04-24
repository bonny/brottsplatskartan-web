**Status:** klar 2026-04-24
**Senast uppdaterad:** 2026-04-24

# Todo #13 — Kommunicera "Hosted in EU"

## Utfört

- Diskret badge i footern (`resources/views/parts/sitefooter.blade.php`)
  — "Servrar i EU 🇪🇺 (Finland)" bredvid Cookies/Sekretesspolicy,
  länkad till `/sida/om`.
- Ny sektion "Servrar i EU 🇪🇺" på `/sida/om` med förklaring att
  servrarna körs hos Hetzner i Helsingfors under GDPR.

## Sammanfattning

Efter flytten från Digital Ocean (USA) till Hetzner (Helsinki, Finland)
ligger sajten nu i EU. Det är en meningsfull differentiering som kan
kommuniceras till besökarna — både av integritetsskäl (GDPR,
datasuveränitet) och som ett värdeförslag mot amerikanska alternativ.

## Möjliga platser att visa det

- **Footer:** liten "🇪🇺 Hosted in EU (Finland)"-badge
- **`/om`-sidan:** dedikerad sektion om hosting och datahantering
- **Meta/beskrivning:** kan nämnas kort i bio/about
- **robots.txt-header eller `llms.txt`:** signal till AI-agenter

## Frågor att besvara

- Vilken ton? Diskret badge vs explicit stolthet?
- Ska det länka till en "Varför EU"-sida eller bara stå som faktaangivelse?
- Finns fler tekniska fakta som passar ihop (t.ex. "Ingen tracking utöver
  Google Analytics", "Open source-kod på GitHub")?

## Status

Ej påbörjat. Låg prio — gör efter att cutovern stabiliserat sig.
