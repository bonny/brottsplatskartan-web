<?php

namespace App\Http\Controllers;

use App\Models\VMAAlert;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Contracts\View;
use \Illuminate\Contracts\View\Factory as ViewFactory;

class VMAAlertsController extends Controller
{
  function exampleAlerts(Request $request)
  {

    $json_data = '
        {
            "timestamp": "2022-05-09T12:34:43+02:00",
            "alerts": [
                {
                    "identifier": "SRCAP20220524164316I",
                    "sender": "https://vmaapi.sr.se/api/v2",
                    "sent": "2022-05-24T14:43:16+02:00",
                    "status": "Actual",
                    "msgType": "Cancel",
                    "scope": "Public",
                    "references": "https://vmaapi.sr.se/api/v2,SRCAP20220524111301I,2022-05-24T09:13:01+02:00",
                    "incidents": "SRVMA20220524111301I",
                    "info": null
                },
                {
                    "identifier": "SRCAP20220524111301I",
                    "sender": "https://vmaapi.sr.se/api/v2",
                    "sent": "2022-05-24T11:13:01+02:00",
                    "status": "Actual",
                    "msgType": "Alert",
                    "scope": "Public",
                    "references": null,
                    "incidents": "SRVMA20220524111301I",
                    "info": [
                      {
                        "language": "sv-SE",
                        "category": "Safety",
                        "event": "Viktigt meddelande till allmänheten (VMA)",
                        "urgency": "Immediate",
                        "severity": "Severe",
                        "certainty": "Observed",
                        "senderName": "Sveriges Radio",
                        "description": "Viktigt meddelande till allmänheten på Hisingen i Göteborgs kommun, Västra Götalands län.\r\n\r\nDet brinner i en industribyggnad på Kongahällavägen, mellan Björlanda och Säve flygplats, med kraftig rökutveckling.\r\n\r\nRäddningsledaren uppmanar alla i området inom en radie av 3 km att gå inomhus och stänga dörrar, fönster och ventilation. \r\n\r\nFör mer information lyssna på Sveriges Radio P4 Göteborg.",
                        "web": "https://sverigesradio.se/artikel/vma-vad-ar-det",
                        "area": [
                          {
                            "areaDesc": "Göteborgs kommun (Västra Götalands län)",
                            "geocode": [
                              {
                                "valueName": "Kommun",
                                "value": "1480"
                              }
                            ]
                          }
                        ]
                      }
                    ]
                  },
                {
                    "identifier": "SRCAP20210531150110I",
                    "sender": "https://vmaapi.sr.se/api/v2",
                    "sent": "2021-05-31T15:01:10+02:00",
                    "status": "Actual",
                    "msgType": "Alert",
                    "scope": "Public",
                    "references": null,
                    "incidents": "SRVMA20210531150110I",
                    "info": [
                      {
                        "language": "sv-SE",
                        "category": "Safety",
                        "event": "Viktigt meddelande till allmänheten (VMA)",
                        "urgency": "Immediate",
                        "severity": "Severe",
                        "certainty": "Observed",
                        "senderName": "Sveriges Radio",
                        "description": "Viktigt meddelande till allmänheten i Landskrona i Skåne län.\r\n\r\nDet brinner i ett fartyg med skrot i Landskrona hamn. \r\n\r\nRäddningsledaren uppmanar alla i området där det finns rök att gå inomhus och stänga dörrar, fönster och ventilation.\r\n\r\nFör mer information lyssna på Sveriges Radio P4 Kristianstad eller P4 Malmöhus.",
                        "web": "https://sverigesradio.se/sida/artikel.aspx?programid=183&amp;artikel=4489542",
                        "area": [
                          {
                            "areaDesc": "Skåne län",
                            "geocode": [
                              {
                                "valueName": "Län",
                                "value": "12"
                              }
                            ]
                          }
                        ]
                      }
                    ]
                  },
              {
                "identifier": "SRCAP20220509143430I",
                "sender": "https://vmaapi.sr.se/api/v2",
                "sent": "2022-05-09T14:34:30+02:00",
                "status": "Actual",
                "msgType": "Alert",
                "scope": "Public",
                "references": null,
                "incidents": "SRVMA20220509143430I",
                "info": [
                  {
                    "language": "sv-SE",
                    "category": "Safety",
                    "event": "Viktigt meddelande till allmänheten (VMA)",
                    "urgency": "Immediate",
                    "severity": "Severe",
                    "certainty": "Observed",
                    "senderName": "Sveriges Radio",
                    "description": "Viktigt meddelande till allmänheten i Uppsala i Uppsala kommun, Uppsala län.\r\n\r\nDet brinner kraftigt i en returpappersfabrik i Boländerna med kraftig rökutveckling som följd.\r\n\r\nRäddningsledaren uppmanar alla i området att gå inomhus och stänga dörrar, fönster och ventilation.\r\n\r\nFör mer information lyssna på Sveriges Radio P4 Uppland.",
                    "web": "https://sverigesradio.se/artikel/vma-vad-ar-det",
                    "area": [
                      {
                        "areaDesc": "Uppsala kommun (Uppsala län)",
                        "geocode": [
                          {
                            "valueName": "Kommun",
                            "value": "0380"
                          }
                        ]
                      }
                    ]
                  }
                ]
              }
            ]
          }
        ';

    $alerts = json_decode($json_data, JSON_PRETTY_PRINT);
    return response()->json($alerts);
  }

  /**
   * Importera VMA från SR:s API.
   * 
   * @return void 
   */
  public static function import()
  {
    $response = Http::get(config('app.vma_alerts_url'));
    $json = $response->json();
    $alerts = collect($json['alerts']);
    $importedAlerts = collect();
    
    $alerts->each(function ($alert) use ($importedAlerts) {
      $alertCollection = collect($alert);

      $alertCollection = $alertCollection->only([
        'identifier',
        'sent',
        'status',
        'msgType',
        'references',
        'incidents',
      ]);

      $alertCollection['sent'] = new Carbon($alertCollection['sent']);

      $alertCollection->put('original_message', $alert);

      $alert = VMAAlert::updateOrCreate(
        [
          'identifier' => $alertCollection->get('identifier')
        ],
        $alertCollection->toArray()
      );

      if ($alert->wasRecentlyCreated || $alert->wasChanged()) {
        Cache::forget('vma_alerts');
        Cache::forget('archived_vma_alerts');
        Cache::forget('current_vma_alerts');
      }

      $importedAlerts->push($alert);
    });

    return ['importedAlerts' => $importedAlerts];
  }

  public function index()
  {
    $alerts = \App\Helper::getVMAAlerts();
    $currentAlerts = \App\Helper::getCurrentVMAAlerts();

    $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('VMA', route("vma-overview"));

    return view(
      'vma-overview', 
      [
        'alerts' => $alerts, 
        'currentAlerts' => $currentAlerts,
        'breadcrumbs' => $breadcrumbs,
      ]
    );
  }

  public function single(Request $request, string $slug)
  {
    $id = Str::of($slug)->explode('-')->last();
    $alert = VMAAlert::findOrFail($id);
    $title = $alert->getShortDescription() . " " . $alert->getDescriptionSecondLine();

    $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('VMA', route("vma-overview"));
    $breadcrumbs->addCrumb( $alert->getHumanSentDateTime() . ': ' . $alert->getDescriptionSecondLine() );

    return view(
      'vma-single',
      [
        'alert' => $alert,
        'title' => $title,
        'breadcrumbs' => $breadcrumbs,
        'canonicalLink' => route('vma-single', ['slug' => $alert->getSlug()]),
      ]
    );
  }

  public function text(Request $request, string $slug)
  {

    $breadcrumbs = new \Creitive\Breadcrumbs\Breadcrumbs();
    $breadcrumbs->setDivider('›');
    $breadcrumbs->addCrumb('Hem', '/');
    $breadcrumbs->addCrumb('VMA', route("vma-overview"));

    switch ($slug) {
      case 'om-vma':
        $title = 'Vad är VMA?';
        $breadcrumbs->addCrumb($title, route("vma-textpage", $slug));
        $text = '

VMA är en förkortning av **Viktigt meddelande till allmänheten**, 
och det är ett varningssystem som används vid olyckor, allvarliga händelser och störningar i viktiga samhällsfunktioner.

VMA handlar om att göra människor uppmärksamma på en omedelbar risk
eller hot och informera om hur berörda människor omedelbart ska agera 
för att skydda sig. 

Vid VMA avbryter t.ex. Sveriges Radio sändningarna för att snabbt få ut informationen och
skickar även vidare informationen till bland andra SVT och TV4. 

På [SVT:s Text-TV](https://texttv.nu/) skriver man när en VMA-situation pågår. På [startsidan 100](https://texttv.nu/100)
står det att ett VMA är utfärdat och hänvisar för mer information till [Text-TV sidan 599](https://texttv.nu/) där mer information
om VMA-meddelandet finns att läsa.

**Meddelandet VMA skickas ut i flera olika kanaler:**

- via radio och tv
- från Krisinformation.se via webb, app och i sociala medier
- som notis i Sveriges Radios app SR Play.
- via utomhusvarning i form av ljudsändare, i folkmun kallat "Hesa Fredrik"

**Det är inte vem som helst som kan skicka ut ett VMA.**

Behöriga att begära VMA är bland andra

- räddningsledare för kommunal och statlig räddningstjänst
- Polisen
- smittskyddsläkare
- SOS Alarm
- ledningen vid anläggning med farlig verksamhet (enligt lagen om skydd mot olyckor).

Läs mer om [hur ett VMA begärs hos Myndigheten för samhällsskydd och beredskap](https://www.msb.se/sv/amnesomraden/krisberedskap--civilt-forsvar/befolkningsskydd/varningssystem/hur-ett-vma-begars-ny/).

---

Källor och mer information:
- https://www.msb.se/VMA
- https://www.krisinformation.se/detta-gor-samhallet/vma-sa-varnas-allmanheten
- https://www.msb.se/sv/amnesomraden/krisberedskap--civilt-forsvar/befolkningsskydd/varningssystem/hur-ett-vma-begars-ny/
- https://sverigesradio.se/artikel/vma-viktigt-meddelande-till-allmanheten
- https://sverigesradio.se/artikel/vma-vad-ar-det
- https://sv.wikipedia.org/wiki/Viktigt_meddelande_till_allm%C3%A4nheten
        ';
        break;
      case 'vanliga-fragor-och-svar-om-vma':
        $title = "Vanliga frågor och svar om VMA";
        $breadcrumbs->addCrumb($title, route("vma-textpage", $slug));
        $text = '

## Vad betyder VMA?

VMA är en förkortning av _Viktigt meddelande till allmänheten_.

## Vem får begära VMA?

[Enligt MSB](https://www.msb.se/sv/amnesomraden/krisberedskap--civilt-forsvar/befolkningsskydd/varningssystem/hur-ett-vma-begars-ny/) får följande begära VMA:

- Räddningschef/räddningsledare för statlig eller kommunal räddningstjänst
- Polismyndighet
- Räddningsledare för statlig räddningstjänst, Strålsäkerhetsmyndigheten
- Smittskyddsläkare
- Anläggningar med farlig verksamhet, (enligt 2 kap, 4 §, Lagen om skydd mot olyckor).
- SOS Alarm
- Svenska kraftnät och de största el- distributörerna
- Teleoperatörer med egna nät
- Alla som får begära sändning av varningsmeddelande
- Regeringen med regeringskansliet. Alla bevakningsansvariga myndigheter. Kommuner och landsting.
- Försvarsmakten  

## När testas VMA?

Signalerna **Viktigt Meddelande** och **Faran över** 
testas 15.00 första helgfria måndag i mars, juni, september och december.

## Hur låter VMA-signalen?

VMA ljuder med en sju sekunders signal omväxlande med 14 sekunders tystnad under minst två minuter.

## Vad är det för lång signal jag hörde?

Det var nog **Faran Över** som signaleras med en 30-40 sekunder lång signal.

--- 

Hos Krisinformation och MSB kan du läsa fler vanliga frågor och svar:
- https://www.msb.se/sv/amnesomraden/krisberedskap--civilt-forsvar/befolkningsskydd/varningssystem/fragor-och-svar-om-utomhusvarning-och-vma-ny/
- https://www.krisinformation.se/detta-gor-samhallet/vma-sa-varnas-allmanheten/faq
        ';
        break;
      default:
        $title = '';
        $text = '';
    }

    $text = Str::markdown($text ?? '');

    return view(
      'vma-text', 
      [
        'slug' => $slug, 
        'title' => $title, 
        'text' => $text,
        'breadcrumbs' => $breadcrumbs,
      ]
    );
  }
}
