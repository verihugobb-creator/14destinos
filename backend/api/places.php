<?php
declare(strict_types=1);

/**
 * Places API — proxy to Nominatim + local cache
 * Handles search and reverse geocoding
 */
class PlacesController
{
    private const USER_AGENT = '14Destinos/1.0 (travel app; contact@14destinos.app)';
    private const NOMINATIM  = 'https://nominatim.openstreetmap.org';

    /**
     * Search places globally
     * GET /places/search?q=barcelona&country=ES&limit=20
     */
    public static function search(): never
    {
        $q       = trim($_GET['q']   ?? '');
        $country = strtoupper(trim($_GET['country'] ?? ''));
        $limit   = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $lang    = $_GET['lang'] ?? 'es';

        if (strlen($q) < 2) jsonError(400, 'Query must be at least 2 characters');

        // Build Nominatim URL
        $params = [
            'q'              => $q,
            'format'         => 'json',
            'limit'          => $limit,
            'accept-language'=> $lang,
            'addressdetails' => '1',
            'featuretype'    => 'settlement',
        ];
        if ($country) $params['countrycodes'] = strtolower($country);

        $url = self::NOMINATIM . '/search?' . http_build_query($params);

        $result = self::fetch($url);
        if ($result === null) {
            // Fallback to our local database if Nominatim fails
            jsonResponse(self::localSearch($q, $country, $limit));
        }

        $places = array_map(fn($r) => [
            'name'         => $r['name'] ?? $r['display_name'] ?? '',
            'display_name' => $r['display_name'] ?? '',
            'country_code' => strtoupper($r['address']['country_code'] ?? ''),
            'country'      => $r['address']['country'] ?? '',
            'region'       => $r['address']['state'] ?? $r['address']['county'] ?? '',
            'latitude'     => (float)($r['lat'] ?? 0),
            'longitude'    => (float)($r['lon'] ?? 0),
            'place_type'   => $r['type'] ?? $r['class'] ?? '',
            'osm_id'       => ($r['osm_type'] ?? '') . ($r['osm_id'] ?? ''),
        ], $result);

        jsonResponse($places);
    }

    /**
     * Get all places in a country ordered by population
     * GET /places/country/ES?limit=500
     */
    public static function byCountry(string $code): never
    {
        $limit = min(500, max(10, (int)($_GET['limit'] ?? 200)));
        $lang  = $_GET['lang'] ?? 'es';

        $params = [
            'country'        => strtolower($code),
            'featureClass'   => 'P',
            'format'         => 'json',
            'limit'          => $limit,
            'accept-language'=> $lang,
            'addressdetails' => '0',
        ];

        // Use Nominatim search with country filter
        $params2 = [
            'q'              => '[place]',
            'countrycodes'   => strtolower($code),
            'format'         => 'json',
            'limit'          => $limit,
            'accept-language'=> $lang,
            'addressdetails' => '1',
        ];

        $url = self::NOMINATIM . '/search?' . http_build_query([
            'countrycodes'   => strtolower($code),
            'q'              => '',
            'format'         => 'json',
            'limit'          => $limit,
            'accept-language'=> $lang,
            'addressdetails' => '1',
            'featuretype'    => 'settlement',
        ]);

        // For country listing, better to use our local DB
        $places = self::localByCountry($code, $limit);
        jsonResponse($places);
    }

    /**
     * Reverse geocode coordinates
     * GET /places/reverse?lat=41.38&lng=2.17
     */
    public static function reverse(): never
    {
        $lat = (float)($_GET['lat'] ?? 0);
        $lng = (float)($_GET['lng'] ?? 0);

        if (!$lat || !$lng) jsonError(400, 'lat y lng son requeridos');

        $params = [
            'lat'            => $lat,
            'lon'            => $lng,
            'format'         => 'json',
            'accept-language'=> 'es',
            'addressdetails' => '1',
            'zoom'           => '14',
        ];

        $url    = self::NOMINATIM . '/reverse?' . http_build_query($params);
        $result = self::fetch($url);

        if (!$result) jsonError(500, 'No se pudo obtener la ubicación');

        $addr = $result['address'] ?? [];
        jsonResponse([
            'name'         => $addr['town'] ?? $addr['village'] ?? $addr['city'] ?? $addr['hamlet'] ?? $addr['suburb'] ?? $result['display_name'] ?? '',
            'display_name' => $result['display_name'] ?? '',
            'country_code' => strtoupper($addr['country_code'] ?? ''),
            'country'      => $addr['country'] ?? '',
            'region'       => $addr['state'] ?? $addr['county'] ?? '',
            'latitude'     => $lat,
            'longitude'    => $lng,
            'road'         => $addr['road'] ?? '',
            'postcode'     => $addr['postcode'] ?? '',
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private static function fetch(string $url): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 8,
                'ignore_errors' => true,
                'header'        => 'User-Agent: ' . self::USER_AGENT . "\r\n",
            ],
            'ssl'  => ['verify_peer' => false],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) return null;

        $data = json_decode($raw, true);
        return $data ?: null;
    }

    private static function localSearch(string $q, string $country, int $limit): array
    {
        $q = '%' . $q . '%';
        $params = [$q, $limit];
        $where  = 'name LIKE ?';

        if ($country) {
            $where   .= ' AND country_code = ?';
            $params   = [$q, $country, $limit];
        }

        $rows = DB::fetchAll(
            "SELECT name, country_code, country_name as country, region, latitude, longitude, place_type
             FROM places WHERE {$where} ORDER BY population DESC LIMIT ?",
            $params
        );

        return $rows ?: [];
    }

    private static function localByCountry(string $code, int $limit): array
    {
        try {
            $rows = DB::fetchAll(
                'SELECT name, country_code, country_name as country, region, latitude, longitude, place_type, population
                 FROM places WHERE country_code = ? ORDER BY population DESC LIMIT ?',
                [strtoupper($code), $limit]
            );
            return $rows ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
