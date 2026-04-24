**Status:** avfärdad 2026-04-21
**Senast uppdaterad:** 2026-04-21

# Todo #9 — Extern DB-backup

## Beslut

Avfärdad. Hetzner tar automatiska snapshots av hela servern, vilket
täcker MariaDB-datamappen. För den här sajten (publik brottsdata,
ingen användardata) är det tillräckligt — ingen separat extern
DB-backup behövs.

## Motivering

- Datat är publikt och reproducerbart från Polisens RSS-flöden + TextTV.
  En total förlust är irriterande men inte katastrofal — fetch-pipelinen
  kan återskapa de flesta events inom några dagar.
- Hetzner-snapshots är billiga, automatiska och räcker för disaster
  recovery.
- Extern backup (S3-dumps o.likn.) kostar tid att sätta upp och
  underhålla för marginell vinst.

Om datat någonsin blir kritiskt (användarkonton, kommentarer,
användargenererat innehåll) — öppna ny todo då.
