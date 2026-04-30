**Status:** aktiv (skissad — koden finns men `isPressNotice()` är avstängd)
**Senast uppdaterad:** 2026-04-30
**Källa:** Inbox Brottsplatskartan (2026-04-30)

# Todo #53 — Återaktivera presstalesperson-filter med smartare logik

## Sammanfattning

> https://brottsplatskartan.se/vastra-gotalands-lan/ovrigt-vastra-gotalands-lan-501252
> Händelser som denna borde inte komma med, iaf inte synas publikt.

URL:n ovan är en presstalesperson-notis ("Efter klockan 21:45 00 finns
ingen presstalesperson i tjänst…") som filtreras till `is_public=false` av
`ContentFilterService::isPressNotice()` — **men anropet är utkommenterat**
i `shouldBePublic()`. Det är fortfarande publikt synligt.

## Bakgrund

I `app/Services/ContentFilterService.php` rad 23–29:

```php
// Presstalesperson-filtret är avaktiverat tills vidare.
// TODO: Återaktivera med bättre logik som bara filtrerar händelser
// som enbart handlar om presstalespersonens tillgänglighet och inte
// innehåller faktisk händelsedata (t.ex. sammanfattningar).
// if ($this->isPressNotice($event)) {
//     return false;
// }
```

Filtret stängdes av sannolikt för att det fångade nattsammanfattningar
som **både** innehåller riktig händelsedata **och** en avslutande
"presstalesperson tjänstgör till…"-fras. Pressnummer-filtret
(`isPhoneNumberInfo()`) är aktivt och fungerar.

Mönstren i `isPressNotice()` är breda — t.ex. `/presstalesperson.*tjänst/i`
träffar nattsammanfattningar med "presstalesperson är i tjänst till 21:00".

## Förslag

**Viktigt:** titeln kan innehålla "presstalesperson"-fras **även när
brödtexten har riktig händelsedata** (t.ex. nattsammanfattning med
press-info som rubrik men flera olyckor/inbrott i body). Filtret får
inte avgöras på titel ensam — det måste titta på vad **brödtexten**
faktiskt innehåller.

Smartare detektering — kombinera mönster med **innehållsanalys**:

1. **Anropet aktiveras** i `shouldBePublic()` igen.
2. **isPressNotice() byggs om så det kräver att brödtexten i huvudsak
   är press-info, inte bara att titeln matchar:**
    - Räkna ut hur stor andel av `parsed_content` (inte `title`) som
      matchar press-mönstren. Om > 70 % av brödtexten består av
      press-fras-tecken → notis. Annars: släpp igenom (innehåller
      händelsedata).
    - Kort body (< 150 tecken) + press-fras-träff → notis.
    - Lång body (≥ 150 tecken) som innehåller både press-fras **och**
      annat ord-innehåll → sammanfattning/händelse, **släpp igenom**.
    - Titel-only-träff utan body-träff → släpp igenom (titeln ljuger
      ofta jämfört med innehållet).
3. **Backfill-körning:** `crimeevents:check-publicity --apply --since=365`
   efter aktivering (kommandot finns redan).
4. **Test:** stickprov på 20 events från `Övrigt`-kategorin —
   manuellt bekräfta att events med riktig händelsedata förblir publika
   även när rubriken låter som press-info.

## Risker

- **False positives på nattsammanfattningar.** Mönstret måste testas
  brett innan backfill — kör först som dry-run
  (`crimeevents:check-publicity --since=30` utan `--apply`) och inspektera
  utfallet manuellt.
- **Pressnummer/presstalesperson-mönstren överlappar** —
  `isPhoneNumberInfo()` fångar redan en del; var noga med att inte
  dubbel-räkna.

## Confidence

**Hög.** Koden finns, kommandot finns, testdata finns. 1–2 timmars jobb plus
stickprovsverifiering.

## Nästa steg

1. Identifiera 5 representativa events: 2 rena press-notiser, 2
   nattsammanfattningar, 1 vanlig händelse. Kör nuvarande
   `isPressNotice()` mot dem och dokumentera utfall.
2. Skriv ny logik (proportions/längd-baserad).
3. Lägg till PHPUnit-test för regressioner.
4. Aktivera + dry-run över 30d → inspektera lista → backfill 365d.
