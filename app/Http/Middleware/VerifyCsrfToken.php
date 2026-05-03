<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

class VerifyCsrfToken extends BaseVerifier
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/',
        'https://brottsplatskartan.se',
        'https://brottsplatskartan.se/',
        // Tracking-pixlar via POST — anrops av navigator.sendBeacon utan CSRF-token.
        'pixel',
        'pixel-sok',
    ];
}
