# Todos – konvention

Ett index över förbättringsarbete ligger i [`/todo.md`](../todo.md). Den
här mappen innehåller en fil per todo med fullständig analys, risker,
fördelar, öppna frågor och föreslagen plan.

## Mappstruktur

- `todos/` — aktiva todos
- `todos/done/` — klara todos (datum i filens header)
- `todos/rejected/` — avfärdade eller sammanslagna

Filer flyttas mellan mapparna när status ändras. Inget raderas — historiken
behålls för spårbarhet och eftersökning.

## Filhuvud

Varje todo-fil börjar med:

```markdown
**Status:** aktiv | pausad | blockerad | klar YYYY-MM-DD | avfärdad YYYY-MM-DD
**Senast uppdaterad:** YYYY-MM-DD
**Blockerad av:** #N (om relevant)
```

Nummer matchar filnamn (`NN-kort-slug.md`) och raden i `todo.md`.

## Synka index

När en todo ändrar status:

1. Uppdatera fil-headern (`Status:` + `Senast uppdaterad:`).
2. Flytta filen till rätt mapp (`git mv`).
3. Uppdatera `/todo.md` så raden hamnar i rätt tabell.
