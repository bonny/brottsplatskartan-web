<?php

namespace App;

use Carbon\Carbon;

class DeployInfo {

    /** Per-request memoization. false = laddat och saknas, array = laddat och finns. */
    private static array|false|null $cache = null;

    /**
     * Läs deploy-info skriven av deploy/deploy.sh.
     *
     * Returnerar null lokalt eller om filen saknas/är trasig — sidfoten
     * gömmer då hela raden. Tider parsas till Europe/Stockholm så att
     * vyn alltid visar svensk lokaltid oavsett serverns timezone.
     *
     * @return array{sha:string,short_sha:string,subject:string,deployed_at:Carbon}|null
     */
    public static function current(): ?array {
        if (self::$cache !== null) {
            return self::$cache === false ? null : self::$cache;
        }

        $path = storage_path('app/deploy.json');
        if (!is_file($path)) {
            self::$cache = false;
            return null;
        }

        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || empty($data['sha']) || empty($data['deployed_at'])) {
            self::$cache = false;
            return null;
        }

        $data['deployed_at'] = Carbon::parse($data['deployed_at'])
            ->setTimezone('Europe/Stockholm')
            ->locale('sv');

        self::$cache = $data;
        return $data;
    }
}
