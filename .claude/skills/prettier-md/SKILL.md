---
name: prettier-md
description: "Use after editing any Markdown (.md) file in this repo. Kör `prettier --write` på de ändrade filerna så formatering (radbrytning, tabeller, listor) blir konsekvent. Gäller README, AGENTS.md, CLAUDE.md, docs/, todos/ och allt annat .md."
---

# Prettier på Markdown

## När den ska användas

Kör automatiskt efter att du har ändrat, skapat eller renamed en eller
flera `.md`-filer i detta repo. Kör **efter** att ändringarna är sparade,
inte innan — så att prettier arbetar mot den slutgiltiga versionen.

Hoppa över när:

- Ändringen är en ren fil-radering (inget att formatera).
- Filen ligger i `vendor/`, `node_modules/` eller liknande
  tredjepartsmappar — de ska inte röras.
- Användaren explicit ber om "ingen formatering" eller felsöker prettier-config.

## Hur

Kör lokalt via host-prettier (finns redan på maskinen, 3.7+):

```bash
prettier --write <file1.md> <file2.md> ...
```

Alternativ om `prettier` inte är i PATH:

```bash
npx prettier --write <file.md>
```

Kör bara på de filer du faktiskt ändrade — inte hela `**/*.md`. Det
undviker ovidkommande diffar i andra dokument.

## Efter körning

- Verifiera att diff:en fortfarande stämmer med intentionen (prettier
  kan t.ex. brytta om långa rader). Läs den formaterade filen om
  innehållet är kritiskt.
- Inkludera formatering i samma commit som innehållsändringen.

## Varför

- Konsekvent formatering mellan manuella och AI-genererade ändringar.
- Färre meningslösa diffar i git (brytpunkter, tabeller).
- Billigt — en sekund per fil.

## Kontext

- Prettier finns globalt installerat (via nvm/npm på hosten, v3.7+).
- Repo saknar egen `.prettierrc*` → defaults används. Om vi senare
  lägger till config plockas den upp automatiskt.
- Skillen gäller även filer i `todos/` (djupdykningarna per todo).
