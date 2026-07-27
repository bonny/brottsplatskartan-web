**Status:** aktiv — konfigureras utanför repot
**Senast uppdaterad:** 2026-07-27

# Todo #96 — CMP: avvisa-alla i lager 1, och vikten före LCP

Från granskningen 2026-07-27. Google Funding Choices,
`fundingchoicesmessages.google.com`, konfigureras i Googles gränssnitt och
inte i repot.

## Uppmätt

- **Inget "Avvisa alla" i första lagret.** Bara "Jag samtycker" och
  "Hantera alternativ". Den som vill neka måste två nivåer ner.
- **3 882 DOM-noder** injiceras av CMP:n innan något annat kan hända.
- Dialogen täcker kartan helt på desktop och 88 % av skärmen på mobil.
  Första intrycket av sajten är en juridisk vägg, inte en karta.
- Texten anger att data delas med **210 partners**.

## Varför det är värt att åtgärda

Två skäl, oberoende av varandra:

1. **Regelrisk.** Avsaknad av avvisa-alla i lager 1 är en känd EU-/IMY-risk.
2. **Mätdata.** Den som vill neka men inte hittar knappen lämnar i stället.
   Det syns som bounce, inte som nekat samtycke.

Funding Choices stödjer att lägga till en avvisa-knapp i lager 1.

## Kräver

Åtkomst till Funding Choices-gränssnittet. Ingen kodändring i repot.
