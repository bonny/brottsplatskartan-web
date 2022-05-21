<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VMAAlerts extends Controller
{
    function alerts (Request $request) {

        $json_data = '
        {
            "timestamp": "2022-05-09T12:34:43+02:00",
            "alerts": [
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
}
