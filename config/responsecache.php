<?php

use App\CacheProfiles\BrottsplatskartanCacheProfile;
use Spatie\ResponseCache\Hasher\DefaultHasher;
use Spatie\ResponseCache\Replacers\CsrfTokenReplacer;
use Spatie\ResponseCache\Serializers\JsonSerializer;

return [
    'enabled' => env('RESPONSE_CACHE_ENABLED', true),

    'cache' => [
        'store' => env('RESPONSE_CACHE_DRIVER', 'file'),
        'lifetime_in_seconds' => (int) env('RESPONSE_CACHE_LIFETIME', 60 * 30),
        'tag' => env('RESPONSE_CACHE_TAG', ''),
    ],

    'bypass' => [
        'header_name' => env('CACHE_BYPASS_HEADER_NAME'),
        'header_value' => env('CACHE_BYPASS_HEADER_VALUE'),
    ],

    'debug' => [
        'enabled' => env('APP_DEBUG', false),
        'cache_time_header_name' => 'X-Cache-Time',
        'cache_status_header_name' => 'X-Cache-Status',
        'cache_age_header_name' => 'X-Cache-Age',
        'cache_key_header_name' => 'X-Cache-Key',
    ],

    // Query-parametrar som inte ska påverka cache-nyckeln.
    // Ersätter vår tidigare CustomRequestHasher från v7.
    'ignored_query_parameters' => [
        't',
        '_',
        'nocache',
        'timestamp',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
    ],

    'cache_profile' => BrottsplatskartanCacheProfile::class,

    'hasher' => DefaultHasher::class,

    'serializer' => JsonSerializer::class,

    'replacers' => [
        CsrfTokenReplacer::class,
    ],
];
