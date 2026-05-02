<?php

// Källval och juridisk grund: tmp-news-research/news-rss-tos-2026-05-01.md.
// TT, Omni och DI är medvetet uteslutna (ToS / ingen feed / fel fokus).
return [
    'feeds' => array_merge(
        [
            ['source' => 'google-news-se', 'url' => 'https://news.google.com/rss?hl=sv&gl=SE&ceid=SE:sv'],

            ['source' => 'svt', 'url' => 'https://www.svt.se/rss.xml'],
            ['source' => 'svt-inrikes', 'url' => 'https://www.svt.se/nyheter/inrikes/rss.xml'],
        ],
        array_map(
            fn (string $slug) => [
                'source' => "svt-{$slug}",
                'url' => "https://www.svt.se/nyheter/lokalt/{$slug}/rss.xml",
            ],
            [
                'blekinge', 'dalarna', 'gavleborg', 'halland', 'helsingborg',
                'jamtland', 'jonkoping', 'norrbotten', 'skane', 'smaland',
                'stockholm', 'sormland', 'uppsala', 'varmland', 'vast',
                'vasterbotten', 'vasternorrland', 'vastmanland', 'orebro', 'ost',
            ]
        ),
        [
            ['source' => 'aftonbladet', 'url' => 'https://rss.aftonbladet.se/rss2/small/pages/sections/senastenytt'],

            ['source' => 'expressen', 'url' => 'https://feeds.expressen.se/nyheter/'],
            ['source' => 'expressen-gt', 'url' => 'https://feeds.expressen.se/gt/'],
            ['source' => 'expressen-kvp', 'url' => 'https://feeds.expressen.se/kvallsposten/'],

            ['source' => 'dn', 'url' => 'https://www.dn.se/rss/'],
            ['source' => 'dn-sthlm', 'url' => 'https://www.dn.se/rss/sthlm/'],

            ['source' => 'svd', 'url' => 'https://www.svd.se/feed/articles.rss'],
        ]
    ),

    'retention_days' => 90,

    'http_timeout' => 8,
];
