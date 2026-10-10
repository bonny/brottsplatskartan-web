<?php

namespace App\Services;

use App\CrimeEvent;
use App\Lansgeometri;

/**
 * Hälsosiffror för geokodningen över en period (todo #111). Används av
 * `geocode:halsa` och statussidan (/status), så att båda räknar likadant.
 *
 * - Bara publika händelser (CrimeEvents globala scope), samma urval som
 *   baslinjen i #108 och det användarna ser.
 * - Länstest: geokodad punkt mot länsgränsen för `polisen_location_name`
 *   (Polisens län, oberoende av Google). `administrative_area_level_1` duger
 *   inte som facit — den kommer från Google själv.
 * - Precision: `location_geometry_type` för alla, `google_types` +
 *   `partial_match` för händelser geokodade efter 2026-10-10.
 */
class GeokodHalsa
{
    /**
     * Googles typer grupperade till en precisionsklass, mest exakt först.
     * Första gruppen som matchar någon av träffens typer vinner.
     */
    public const PRECISIONSKLASSER = [
        'adress' => ['street_address', 'premise', 'subpremise'],
        'korsning' => ['intersection'],
        'poi' => ['point_of_interest', 'establishment', 'transit_station', 'park', 'airport'],
        'gata' => ['route'],
        'stadsdel' => ['sublocality', 'sublocality_level_1', 'neighborhood'],
        'ort' => ['locality', 'postal_town'],
        'kommun' => ['administrative_area_level_2'],
        'län' => ['administrative_area_level_1'],
    ];

    /**
     * @return array{
     *   antal: int, geokodade: int, lan_testade: int, okant_lan: int,
     *   utanfor: list<array{id: int, lan: ?string, titel: ?string}>,
     *   geometrityper: array<string, int>, klasser: array<string, int>,
     *   med_typer: int, partial: int
     * }
     */
    public function berakna(string $fran, string $till): array
    {
        $resultat = [
            'antal' => 0, 'geokodade' => 0, 'lan_testade' => 0, 'okant_lan' => 0,
            'utanfor' => [], 'geometrityper' => [], 'klasser' => [], 'med_typer' => 0, 'partial' => 0,
        ];

        $query = CrimeEvent::query()
            ->where('created_at', '>=', $fran)
            ->where('created_at', '<', $till)
            ->select([
                'id', 'parsed_title', 'polisen_location_name', 'geocoded',
                'location_lat', 'location_lng', 'location_geometry_type',
                'google_types', 'google_partial_match',
            ]);

        foreach ($query->lazyById(500) as $event) {
            $resultat['antal']++;
            if (! $event->geocoded || ! $event->location_lat) {
                continue;
            }
            $resultat['geokodade']++;

            $typ = $event->location_geometry_type ?: '(saknas)';
            $resultat['geometrityper'][$typ] = ($resultat['geometrityper'][$typ] ?? 0) + 1;

            if ($event->google_types !== null) {
                $resultat['med_typer']++;
                $klass = $this->precisionsklass($event->google_types);
                $resultat['klasser'][$klass] = ($resultat['klasser'][$klass] ?? 0) + 1;
                if ($event->google_partial_match) {
                    $resultat['partial']++;
                }
            }

            $inom = Lansgeometri::innehaller($event->polisen_location_name, (float) $event->location_lat, (float) $event->location_lng);
            if ($inom === null) {
                $resultat['okant_lan']++;
            } else {
                $resultat['lan_testade']++;
                if (! $inom) {
                    $resultat['utanfor'][] = ['id' => $event->id, 'lan' => $event->polisen_location_name, 'titel' => $event->parsed_title];
                }
            }
        }

        arsort($resultat['geometrityper']);
        arsort($resultat['klasser']);

        return $resultat;
    }

    /**
     * @param array<int, string> $types
     */
    public function precisionsklass(array $types): string
    {
        foreach (self::PRECISIONSKLASSER as $klass => $klassTyper) {
            if (array_intersect($types, $klassTyper)) {
                return $klass;
            }
        }

        return 'annan';
    }
}
