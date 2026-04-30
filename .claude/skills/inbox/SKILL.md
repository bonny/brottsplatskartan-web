---
name: inbox
description: "Läs och bearbeta Obsidian-dokumentet `Inbox Brottsplatskartan.md` — snabba idéer/anteckningar användaren har dumpat på språng. Använd när användaren ber om att kika i inboxen, hämta nya idéer, skapa todos från inboxen, t.ex. 'kolla inboxen', 'vad ligger i inboxen', 'skapa todo från inbox #N', 'töm inboxen'. Subkommandon: (tomt)/list, show <nr>, todo <nr> [titel], remove <nr>, process."
---

# /inbox — Obsidian-inbox för Brottsplatskartan

Användaren samlar snabba idéer, länkar och iakttagelser om
[Brottsplatskartan.se](https://brottsplatskartan.se) i ett Obsidian-dokument
när hen är på språng. Den här skillen läser dokumentet, listar items, och
hjälper konvertera relevanta items till todos via `/todo`-konventionen.

## Filplats

Filnamnet är fast:

```
Inbox Brottsplatskartan.md
```

**Sökvägen till Obsidian-vaulten är maskin-specifik** och hämtas från
`CLAUDE.local.md` (gitignored, en per dator) — leta efter raden:

```
OBSIDIAN_VAULT_BPK=<absolut sökväg till vault-mappen>
```

Full sökväg = `$OBSIDIAN_VAULT_BPK/Inbox Brottsplatskartan.md`.

Om `CLAUDE.local.md` saknas eller raden inte finns: stoppa och be
användaren lägga in den (visa exemplet ovan). Gissa aldrig en sökväg —
olika datorer har olika placeringar (Documents, iCloud, Dropbox, etc.).

## Filformat

- Frontmatter (`---` ... `---`) i toppen — hoppa över.
- Korta intro-rader efter frontmattern (t.ex. "Tankar och idéer för…") —
  hoppa över.
- Därefter en serie **items separerade av `---`** (horisontell linje).
- Ett item kan vara: 1–flera rader text, en URL, en bild
  (`![[...]]`), en wiki-länk (`[[...]]`), en lista, eller en kombination.
- Tomma rader inom ett item är okej — gränsen är `---` på egen rad.

## Tolka argumentet

Argumentet efter `/inbox` (ord 1) avgör subkommando:

| Argument            | Subkommando                                 |
| ------------------- | ------------------------------------------- |
| (tomt) / `list`     | Lista alla items                            |
| `show <nr>`         | Visa item N i sin helhet                    |
| `todo <nr> [titel]` | Skapa todo från item N + ta bort från inbox |
| `remove <nr>`       | Ta bort item N från inbox utan todo         |
| `process`           | Gå igenom alla items interaktivt            |

Numret är 1-indexerat baserat på items-ordningen (efter frontmatter+intro).

## list / (tomt)

1. Read inbox-filen.
2. Parsa items (split på rader som är exakt `---`, hoppa frontmatter+intro).
3. Visa varje item som `#N — <första meningen eller URL, max ~80 tecken>`.
4. Om filen är tom (bara frontmatter+intro): säg det.
5. Avsluta med en mening om hur användaren går vidare:
   `/inbox show <nr>` för att se hela, `/inbox todo <nr>` för att konvertera.

## show <nr>

1. Read inbox-filen, hitta item #N.
2. Visa hela innehållet ordagrant (inkl. URLs/bilder).
3. Om N är utanför range: säg hur många items som finns.

## todo <nr> [titel]

Konvertera ett inbox-item till en todo i `todos/`-strukturen.

1. **Läs item #N** från inbox-filen.
2. **Föreslå titel** om användaren inte gav någon:
    - Sammanfatta itemet i 4–8 ord på svenska.
    - URL-only items: använd domän + nyckelord från innehållet.
    - Visa förslaget och be om bekräftelse innan du skapar todon **om**
      itemet är tvetydigt eller har flera möjliga vinklar.
      För uppenbara fall (kort en-rads-idé): kör direkt.
3. **Skapa todo** enligt `/todo new`-konventionen i
   `.claude/skills/todo/SKILL.md`:
    - Hitta nästa nummer (`<NN>`) genom att lista `todos/`,
      `todos/done/` och `todos/rejected/`, ta max prefix + 1, zero-pad.
    - Skapa `todos/<NN>-<slug>.md` med standardhuvudet.
    - **Sammanfattning-fältet** får inbox-itemets text ordagrant
      (inklusive URLs) — användaren har redan formulerat sin tanke.
    - Lägg in en rad `**Källa:** Inbox Brottsplatskartan (YYYY-MM-DD)`
      under filhuvudet så det går att spåra varifrån idén kom.
    - Uppdatera `todo.md` (Aktiva-tabellen + rad 7 "Senast uppdaterad").
4. **Ta bort item från inbox-filen:**
    - Edit inbox-filen — ta bort itemet **och** den efterföljande
      `---`-separatorn (eller den föregående om itemet är sist).
    - Var noga med att inte lämna dubbla `---` eller orphan-tom rad.
5. **Rapportera:** `Skapade #<NN> — <titel>. Tog bort item från inbox.`

## remove <nr>

Användaren bedömer att itemet inte ska bli en todo (för litet, redan
gjort, eller bara en notering).

1. Visa itemets innehåll först så användaren kan dubbelkolla.
2. Be om bekräftelse om itemet inte är trivialt litet.
3. Edit inbox-filen — ta bort itemet + tillhörande `---`-separator.
4. Rapportera: `Tog bort item #N från inbox.`

## process

Interaktivt arbetsflöde för att tömma inboxen.

1. Lista alla items.
2. För varje item, **i ordning**:
    - Visa itemet i sin helhet.
    - Föreslå en av: `todo` (med föreslagen titel), `remove`, eller `skip`
      (lämna kvar, t.ex. om det kräver mer tankearbete).
    - Vänta på användarens beslut innan du går till nästa.
3. Avsluta med en sammanfattning: hur många items som blev todos,
   togs bort, och lämnades kvar.

## Konventioner

- **Items är tankar, inte kommandon.** Användaren har skrivit dem snabbt på
  mobilen — tolka generöst, formatera om vid behov, men behåll alltid
  originalinnehållet i todons Sammanfattning.
- **URLs ska bevaras intakt** — de är ofta poängen med itemet.
- **Bild-referenser** (`![[CleanShot ...]]`) — behåll i todons text. Filen
  ligger i Obsidians attachments-mapp och nås inte från projektet, men
  referensen hjälper användaren att hitta tillbaka.
- **Datum** = `currentDate` från system-context.
- **Aldrig** ta bort items från inbox utan att antingen skapa en todo
  eller få explicit bekräftelse på `remove`.
- **Edita inbox-filen försiktigt** — det är användarens råmaterial, inte
  kontrollerad kod. Bevara frontmatter och intro orört.

## Edge cases

- **Inbox-filen finns inte:** säg det och ge sökvägen — användaren kan
  ha bytt namn eller flyttat filen.
- **Item innehåller bara en bild/URL utan text:** föreslå titel baserad på
  URL/filnamn, fråga användaren om bekräftelse innan todo skapas.
- **Item är en duplikat av en befintlig todo:** sök i `todo.md` och i
  `todos/`-mappen efter nyckelord innan todo skapas. Om duplikat hittas:
  flagga för användaren, föreslå att antingen `remove`-a inbox-itemet
  eller länka det i den existerande todons Bakgrund.
- **`todo.md` saknas eller är skadad:** stoppa och be användaren fixa
  index först — skillen ska inte gissa strukturen.
- **Argument är inte en siffra för `show`/`todo`/`remove`:** lista items
  och be om `<nr>`.
