<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'google' => [
        'signing_secret' => env('GOOGLE_SIGNING_SECRET'),
    ],

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-sonnet-4-5-20250929'),
    ],

    'tileserver' => [
        'url' => env('TILESERVER_URL', 'https://kartbilder.brottsplatskartan.se/'),
        // Kartbild-stil för enskilda händelser: 'circle' (röd tonad cirkel
        // med radie efter geo-precision, default) eller 'bbox' (gammal
        // viewport-rektangel, finns kvar som emergency-rollback).
        // Se todos/20-kartbilder-med-cirklar.md.
        'map_style' => env('TILESERVER_MAP_STYLE', 'circle'),
    ],

    'monthly_views' => [
        // todo #25: aktivera 301-redirect från dagsvyer till månadsvy med
        // dag-anchor för specifika platser/län (pilot-fas).
        // Värden:
        //   ''           — flaggan av (default), inga 301-redirects.
        //   'all'        — alla platser och län 301:as från dagsvy → månadsvy.
        //   'list:a,b,c' — bara dessa platser/län (komma-separerad slug-lista).
        // Slug:ar matchas case-insensitive mot URL-segmentet i dagsvy-routen.
        'pilot' => env('MONTHLY_VIEWS_PILOT', ''),
    ],

    'trafikverket' => [
        // Trafikverket Trafikinformation API (todo #50). CC0-licens, gratis,
        // registrera nyckel: https://data.trafikverket.se/oauth2/Account/register
        'api_key' => env('TRAFIKVERKET_API_KEY', ''),
    ],

];
