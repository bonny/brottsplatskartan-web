**Status:** aktiv — kräver beslut
**Senast uppdaterad:** 2026-07-27

# Todo #93 — Startsidans fold: annonsplats och ticker ovanför H1

Från UX-granskningen 2026-07-27.

## Uppmätt

| Viewport         | Krom   | Annonsplats | H1 hamnar på | Andel av första skärmen före innehåll |
| ---------------- | ------ | ----------- | ------------ | ------------------------------------- |
| Desktop 1440×900 | 155 px | 1440×280    | y=471        | 52 %                                  |
| Mobil 390×844    | 106 px | 375×375     | y=551        | 65 %                                  |

Kartan är sajtens hela värdeerbjudande och syns inte utan att scrolla. För
den som inte samtycker till spårning är de 280/375 pixlarna dessutom bara
grå tomhet.

**Tickern äger sidans första pixel.** Den röda nyhetstickern ligger på y=0,
ovanför loggan, med horisontellt trunkerade rubriker ("…man tvingades
läm…"). Den dubblerar dessutom `Senaste händelserna`-boxen längre ner
exakt — samma poster, samma tidsstämplar.

## Förslag

- Flytta topp-annonsen till anchor (sticky botten), som inte äter fold.
  Beroende: #92.
- Flytta tickern under headern, eller ta bort den. Den dubblerar innehåll
  som redan finns på sidan — se #94.

## Kräver beslut

Både annonsplaceringen och tickerns existens är avvägningar mellan intäkt,
engagemang och första intryck. Ingen av dem kan avgöras från koden.
