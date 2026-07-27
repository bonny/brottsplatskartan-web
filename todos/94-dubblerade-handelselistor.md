**Status:** aktiv — kräver beslut
**Senast uppdaterad:** 2026-07-27

# Todo #94 — Samma händelser visas upp till tre gånger på startsidan

Från UX-granskningen 2026-07-27.

## Uppmätt

Samma händelser förekommer i:

1. **Nyhetstickern** högst upp (`parts/bar-events`)
2. **`Mest läst`**-listan (`parts/events-heroes`, 17 poster)
3. **`Senaste händelserna`** och **`Mest lästa händelserna`** längst ner
   (`x-latest-events-box` + `x-trending-events-box`)

Tickern och `Senaste händelserna` visade identiska poster med identiska
tidsstämplar vid granskningen (05:51 Rån i Flen, 05:49, 05:46 …), och
`Mest lästa händelserna` dubblerar `Mest läst`-listan.

Effekt: sidan är ~11 000 px hög på mobil för att i praktiken visa två
listor, och allt ser redan besökt ut. Sidor/session från `/` på mobil är
2,86 trots 185 länkar på sidan.

## Varför det inte är självklart att bara ta bort

Intern länkning ska inte gömmas — det var motiveringen till att fyra
efter-fix-commits i #71 plockade bort samtliga `MobileCollapse`-toggles
(`4c20209`, `b7ace2e`, `9390924`, `be5d7a2`). Att ta bort en lista tar bort
länkar, inte bara pixlar.

## Kräver beslut

Vilken av de tre listorna som ska bort, eller om de ska differentieras så
de faktiskt visar olika saker (t.ex. `Senaste` = strikt kronologisk,
`Mest läst` = populäritet, utan överlapp). Det är ett innehålls- och
SEO-beslut.
