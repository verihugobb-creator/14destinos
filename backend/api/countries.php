<?php
declare(strict_types=1);

class CountryController
{
    public static function list(): never
    {
        jsonResponse(['message' => 'OK']);
    }

    public static function cities(string $code): never
    {
        $cities = [];

        // Try Teleport API using file_get_contents (usually allowed)
        $url  = "https://api.teleport.org/api/countries/iso_alpha2:{$code}/urban_areas/";
        $ctx  = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $json = @file_get_contents($url, false, $ctx);

        if ($json) {
            $data = json_decode($json, true);
            foreach (($data['_links']['ua:item'] ?? []) as $item) {
                $cities[] = $item['name'];
            }
        }

        // Fallback: get capital from restcountries
        if (!$cities) {
            $url2 = "https://restcountries.com/v3.1/alpha/{$code}?fields=capital";
            $json2 = @file_get_contents($url2, false, $ctx);
            if ($json2) {
                $data2 = json_decode($json2, true);
                $cities = $data2['capital'] ?? [];
            }
        }

        jsonResponse(['country_code' => $code, 'cities' => $cities]);
    }
}
