---
name: todo
description: "Hantera projektets todos i `todo.md` + `todos/`. Använd när användaren frågar/ber om todos — t.ex. 'vad är nästa todo', 'vilka todos har vi', 'visa todo #N', 'markera #N klar', 'avfärda #N', 'skapa todo …'. Subkommandon: (tomt)/list, next, show <nr>, done <nr>, reject <nr>, new <titel>."
---

# /todo — todo-hantering

Todos finns på två platser och måste hållas synkade:

- `todo.md` (repo-roten) — index med tabellerna: **Aktiva**, **Uppföljningar**
  (datum-bundna manuella check-ins), **Klara**, **Avfärdade / sammanslagna**.
  Rad 7 har `Senast uppdaterad: YYYY-MM-DD (+#NN <hint>)` — uppdatera vid varje ändring.
- `todos/<NN>-<slug>.md` — en fil per todo med full analys.
    - `todos/done/` — klara
    - `todos/rejected/` — avfärdade / sammanslagna

Konventionsdokument: `todos/README.md`.

**Uppföljningar-sektionen** är en kronologisk lista över datum-bundna
manuella åtgärder som inte går att autoschemalägga (kräver lokala MCP:s,
SSH-nycklar, eller mänsklig bedömning). Lägg till en rad här när du
upptäcker ett framtida datum att komma ihåg — t.ex. mätperiod-slut, soak-
end, ramp-up av feature flag. Format: `| YYYY-MM-DD | <åtgärd> | [#NN](...)|`.

## Tolka argumentet

Argumentet efter `/todo` (ord 1) avgör subkommando:

| Argument        | Subkommando        |
| --------------- | ------------------ |
| (tomt) / `list` | Lista aktiva       |
| `next`          | Rekommendera nästa |
| `show <nr>`     | Visa todo-fil      |
| `done <nr>`     | Markera klar       |
| `reject <nr>`   | Avfärda            |
| `new <titel>`   | Skapa ny todo      |

Numret matchas mot filnamnets prefix (`<NN>-...`).

## list / (tomt)

1. Read `todo.md`.
2. Visa **Aktiva**-tabellen som en kort lista — `#NN — Titel — Status`.
3. Om "Beroenden" är icke-tom, nämn de relevanta i en mening.
4. **Kolla "Uppföljningar"-sektionen:** om någon rad har datum inom 14 dagar
   från `currentDate`, nämn den/dem ("Nästa uppföljning: YYYY-MM-DD — <åtgärd>").
   Om en uppföljning är **förfallen** (datum < currentDate), flagga det
   tydligt med ⚠ — det betyder en check-in är glömd.
5. Inga andra filer öppnas — bara index.

## next

1. Read `todo.md` — fokus på **Aktiva**-tabellen + sektionen **Föreslagen ordning**.
2. Gå igenom listan i ordning och hitta första todo som är **aktionerbar nu**.
   En todo räknas som blockerad om Status-kolumnen indikerar väntan:
    - Innehåller "väntar på", "soak", "mätperiod", "30d", "60d", "90d", "till YYYY-MM-DD"
    - Har en datumstämpel framåt i tiden jämfört med `currentDate` från system-context
    - "Blockerad av: #N" där #N inte är klar
3. Rekommendera den första aktionerbara. Motivera kort (2–3 meningar): vad det är, varför den är logisk nu, eventuell synergi med nyligen klar todo.
4. Om alla är blockerade: säg det, lista närmaste avblockning (datum) per blockerad rad.
5. **Kolla "Uppföljningar"-sektionen:** om en uppföljning är förfallen
   (datum < currentDate), nämn den separat — det är sannolikt vad
   användaren bör göra istället för en aktiv todo.

## show <nr>

1. Hitta filen: glob `todos/<NN>-*.md` → `todos/done/<NN>-*.md` → `todos/rejected/<NN>-*.md`.
   Använd Bash `ls todos/<NN>-*.md todos/done/<NN>-*.md todos/rejected/<NN>-*.md 2>/dev/null` eller motsvarande glob.
2. Read den fil som matchar.
3. Visa innehållet. För långa filer (>200 rader): summera Sammanfattning/Status/Plan, men visa hela bara om användaren ber.

## done <nr>

Alla steg krävs — det här är hela poängen med skillen.

1. **Hitta filen:** `todos/<NN>-*.md` (måste ligga i aktiv-mappen — om den redan är i `done/` eller `rejected/`, stoppa och säg det).
2. **Uppdatera filhuvudet** (rad 1–2 i todo-filen):
    ```markdown
    **Status:** klar YYYY-MM-DD — <kort sammanfattning vad som gjordes>
    **Senast uppdaterad:** YYYY-MM-DD
    ```
    Datum = `currentDate` från system-context. Sammanfattning = vad användaren just gjort, inte abstrakt.
3. **Flytta filen:** `git mv todos/<NN>-*.md todos/done/` (Bash). Om filen inte är trackad, fall back till `mv`.
4. **Uppdatera `todo.md`:**
    - Ta bort raden från **Aktiva**-tabellen.
    - Lägg till **högst upp** i **Klara**-tabellen (sorterad nyast först):
        ```
        | <NN>  | <Titel utan #-prefix>                              | YYYY-MM-DD | [todos/done/<fil>.md](todos/done/<fil>.md) |
        ```
    - Uppdatera rad 7: `Senast uppdaterad: YYYY-MM-DD (+#<NN> <kort hint>).`
    - Om todon stod i **Föreslagen ordning** — ta bort den raden och numrera om.
    - Om todon var listad i **Beroenden** som blockerare — uppdatera noten till `*(klar YYYY-MM-DD — beroendet löst, listas tills #M startat.)*` (samma stil som befintliga noter där).
5. **Verifiering:** kör `git status` så användaren ser ändrade filer. Commit:a inte utan att användaren ber.

## reject <nr>

Som `done` men:

1. **Filhuvud:** `**Status:** avfärdad YYYY-MM-DD — <skäl>`
2. **Flytta:** `git mv todos/<NN>-*.md todos/rejected/`
3. **Index:** lägg till i tabellen **Avfärdade / sammanslagna**:
    ```
    | <NN>  | <Titel>      | Avfärdad YYYY-MM-DD — <kort skäl>      | [todos/rejected/<fil>.md](todos/rejected/<fil>.md) |
    ```
4. Uppdatera "Senast uppdaterad" på rad 7.
5. Om todon var i Föreslagen ordning eller blockerade andra — uppdatera de sektionerna.

Be användaren om skälet om det inte gavs i argumenten ("Vad är skälet?").

## new <titel>

1. **Hitta nästa nummer:** lista alla `todos/`-, `done/`- och `rejected/`-filer, plocka ut prefix-numret (`<NN>`), ta max + 1. Zero-pad till 2 siffror.
2. **Skapa slug** från titeln: lowercase, mellanslag→bindestreck, ta bort/translitera åäö (`å`/`ä`→`a`, `ö`→`o`), strip-a interpunktion. Max ~50 tecken.
3. **Skapa filen** `todos/<NN>-<slug>.md` med mall:

    ```markdown
    **Status:** aktiv
    **Senast uppdaterad:** YYYY-MM-DD

    # Todo #<NN> — <Titel>

    ## Sammanfattning

    <fyll i — vad är problemet, vad föreslås>

    ## Bakgrund

    ## Förslag

    ## Risker

    ## Confidence

    <låg | medel | hög> — <motivering>
    ```

4. **Uppdatera `todo.md`:**
    - Lägg till en rad i **Aktiva**-tabellen:
        ```
        | <NN>  | <Titel>     | <kort status, t.ex. "ny — analys saknas">  | [todos/<NN>-<slug>.md](todos/<NN>-<slug>.md) |
        ```
    - Uppdatera rad 7: `Senast uppdaterad: YYYY-MM-DD (+#<NN> <titel>).`
5. Säg till användaren att filen skapades med en mall och fråga om de vill fylla i Sammanfattning/Bakgrund nu.

## Konventioner att respektera

- **Datum** = `currentDate` från system-context (ISO `YYYY-MM-DD`). Aldrig hårdkoda.
- **Filnamn**: `<NN>-<slug>.md`, `<NN>` zero-padded två siffror.
- **Tabellsortering** i `todo.md`:
    - Aktiva: ingen särskild ordning.
    - Klara: **nyast först** (datum desc) — nya rader läggs **överst**.
    - Avfärdade: ingen särskild ordning.
- **Aldrig radera** todo-filer — alltid flytta till `done/` eller `rejected/`. Historiken är poängen.
- **Aldrig commita** utan att användaren ber. Skillen levererar arbetsträd-ändringar + `git status`.
- **Beroenden-sektionen** är levande — uppdatera när blockerare blir klara.

## Edge cases

- **Hittar inte todo #N:** lista vad som finns och fråga.
- **Todon är redan i `done/`:** avbryt `done` — säg det. Erbjud `show` istället.
- **Användaren skriver titeln med hashtag** (`/todo done #10`): strip-a `#` innan tolkning.
- **Argument är inte en siffra för `done`/`reject`/`show`:** be om `<nr>`.
- **Redaktionellt skript-fel** (t.ex. `git mv` misslyckas): fall back till `mv` + flagga för användaren.
