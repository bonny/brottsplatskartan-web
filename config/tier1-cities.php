<?php

/**
 * Tier 1-städer — dedikerade /<stad>-sidor (i stället för /plats/<stad>)
 * med extra context (BRÅ-data, Wikidata-fakta, AI-sammanfattningar).
 *
 * URL-slug är ASCII-only ('malmo', 'goteborg'). DB-fälten
 * parsed_title_location och administrative_area_level_2 lagrar display-form
 * ('Malmö', 'Göteborg') — `displayName` översätter slug → DB-form.
 *
 * - kommunKod: SCB:s 4-siffriga kod, används för BRÅ-uppslag.
 * - wikidataQid: identifierare för Wikidata-fakta (description, grundat-år).
 *
 * Lägg till nya städer enligt todo #24 efter SEO-utvärdering.
 */
return [
    'stockholm' => [
        'name' => 'Stockholm och Stockholms län',
        'displayName' => 'Stockholm',
        'lan' => 'Stockholms län',
        'kommunKod' => '0180',
        'lat' => 59.328930,
        'lng' => 18.064910,
        'mapZoom' => 10,
        'distance' => 20,
        'pageTitle' => 'Stockholm: Polishändelser och blåljus',
        'title' => 'Senaste blåljusen och händelser från Polisen.',
        'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Stockholm',
        'wikidataQid' => 'Q1754',
    ],
    'malmo' => [
        'name' => 'Malmö och Skåne län',
        'displayName' => 'Malmö',
        'lan' => 'Skåne län',
        'kommunKod' => '1280',
        'lat' => 55.604981,
        'lng' => 13.003822,
        'mapZoom' => 11,
        'distance' => 15,
        'pageTitle' => 'Malmö: Polishändelser och blåljus',
        'title' => 'Senaste blåljusen och händelser från Polisen i Malmö med omnejd.',
        'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Malmö och Skåne län.',
        'wikidataQid' => 'Q2211',
    ],
    'goteborg' => [
        'name' => 'Göteborg och Västra Götalands län',
        'displayName' => 'Göteborg',
        'lan' => 'Västra Götalands län',
        'kommunKod' => '1480',
        'lat' => 57.708870,
        'lng' => 11.974560,
        'mapZoom' => 10,
        'distance' => 20,
        'pageTitle' => 'Göteborg: Polishändelser och blåljus',
        'title' => 'Senaste blåljusen och händelser från Polisen i Göteborg med omnejd.',
        'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Göteborg och Västra Götalands län.',
        'wikidataQid' => 'Q25287',
    ],
    'helsingborg' => [
        'name' => 'Helsingborg och Skåne län',
        'displayName' => 'Helsingborg',
        'lan' => 'Skåne län',
        'kommunKod' => '1283',
        'lat' => 56.046467,
        'lng' => 12.694512,
        'mapZoom' => 11,
        'distance' => 12,
        'pageTitle' => 'Helsingborg: Polishändelser och blåljus',
        'title' => 'Senaste blåljusen och händelser från Polisen i Helsingborg med omnejd.',
        'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Helsingborg och Skåne län.',
        'wikidataQid' => 'Q25411',
    ],
    'uppsala' => [
        'name' => 'Uppsala och Uppsala län',
        'displayName' => 'Uppsala',
        'lan' => 'Uppsala län',
        'kommunKod' => '0380',
        'lat' => 59.858564,
        'lng' => 17.638927,
        'mapZoom' => 11,
        'distance' => 15,
        'pageTitle' => 'Uppsala: Polishändelser och blåljus',
        'title' => 'Senaste blåljusen och händelser från Polisen i Uppsala med omnejd.',
        'description' => 'Se aktuella polishändelser och blåljuslarm från räddningstjänsten i Uppsala och Uppsala län.',
        'wikidataQid' => 'Q25286',
    ],
];
