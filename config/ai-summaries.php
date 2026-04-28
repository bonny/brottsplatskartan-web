<?php

return [
    /**
     * Områden som ska få automatisk AI-sammanfattning när nya händelser kommer in
     * 
     * Format: 'område' => [
     *     'enabled' => true/false,
     *     'min_events' => minsta antal nya händelser för att trigga uppdatering
     * ]
     */
    'auto_update_areas' => [
        'stockholm' => [
            'enabled' => true,
            'min_events' => 1,
        ],
        'goteborg' => [
            'enabled' => true,
            'min_events' => 1,
        ],
        'malmo' => [
            'enabled' => true,
            'min_events' => 1,
        ],
        'uppsala' => [
            'enabled' => true,
            'min_events' => 1,
        ],
        'helsingborg' => [
            'enabled' => true,
            'min_events' => 1,
        ],
    ],

    /**
     * Fördröjning i minuter innan sammanfattning genereras
     * Detta ger tid för flera händelser att komma in tillsammans
     */
    'update_delay_minutes' => 5,
];