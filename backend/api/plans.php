<?php
declare(strict_types=1);

class PlanController
{
    public static function generate(): never
    {
        $user = Auth::require();
        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];

        $city      = trim($body['city']         ?? '');
        $country   = strtoupper(trim($body['country_code'] ?? ''));
        $days      = max(1, min(14, (int)($body['trip_days'] ?? 3)));
        $interests = array_slice(array_filter((array)($body['interests'] ?? [])), 0, 10);

        if (!$city || !$country) {
            throw new ValidationException('Error de validación', ['city' => 'Ciudad y código de país requeridos.']);
        }

        $plan = self::generatePlan($city, $country, $days, $interests);

        DB::query(
            'INSERT INTO plans (user_id, country_code, city, trip_days, interests, ai_plan)
             VALUES (?,?,?,?,?,?)',
            [$user['id'], $country, $city, $days, json_encode($interests), $plan]
        );

        jsonResponse([
            'city'      => $city,
            'country'   => $country,
            'trip_days' => $days,
            'interests' => $interests,
            'plan'      => $plan,
            'links'     => self::buildLinks($city, $country),
        ], 201);
    }

    public static function index(): never
    {
        $user = Auth::require();
        $rows = DB::fetchAll(
            'SELECT id, country_code, city, trip_days, interests, created_at
             FROM plans WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
            [$user['id']]
        );
        foreach ($rows as &$r) {
            $r['interests'] = json_decode($r['interests'] ?? '[]', true);
        }
        jsonResponse($rows);
    }

    // ── Plan generation ───────────────────────────────────────────────────────
    private static function generatePlan(string $city, string $country, int $days, array $interests): string
    {
        // Try OpenAI if key is set (works on paid hosting)
        if (OPENAI_KEY) {
            $result = self::callOpenAI($city, $country, $days, $interests);
            if ($result) return $result;
        }
        // Fallback: rich template-based plan
        return self::templatePlan($city, $country, $days, $interests);
    }

    private static function callOpenAI(string $city, string $country, int $days, array $interests): ?string
    {
        $interestStr = $interests ? 'Intereses: ' . implode(', ', $interests) . '.' : '';
        $prompt = "Eres un experto planificador de viajes. Crea un plan detallado de {$days} días para visitar {$city} ({$country}). {$interestStr}

Formatea el plan en Markdown con:
- Introducción breve y entusiasta (2-3 frases)
- Para cada día: ### Día N — [Tema del día]
  - **Mañana**, **Tarde**, **Noche** con actividades concretas, tiempos estimados y tips
- Sección final: ### 💡 Consejos prácticos
  - Transporte, presupuesto estimado, mejor época para visitar

Tono: cercano y personal, como un amigo que conoce bien la ciudad.";

        $payload = json_encode([
            'model'      => 'gpt-4o-mini',
            'max_tokens' => 2000,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        // Use file_get_contents as curl may be restricted
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . OPENAI_KEY,
                    'Content-Length: ' . strlen($payload),
                ]),
                'content' => $payload,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents('https://api.openai.com/v1/chat/completions', false, $ctx);
        if (!$response) return null;

        $data = json_decode($response, true);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private static function templatePlan(string $city, string $country, int $days, array $interests): string
    {
        $interestNote = $interests
            ? "\n> 🎯 Plan orientado a: **" . implode('**, **', array_map('htmlspecialchars', $interests)) . "**\n"
            : '';

        $activities = [
            'mañana'  => ['Visita el centro histórico y toma un desayuno local', 'Explora el mercado o mercadillo más famoso', 'Paseo por el barrio más pintoresco', 'Visita el museo principal de la ciudad'],
            'tarde'   => ['Recorrido por los parques y jardines', 'Visita a monumentos y puntos de interés', 'Tarde libre para explorar tiendas y galerías', 'Excursión a las afueras o viewpoint más famoso'],
            'noche'   => ['Cena en un restaurante típico local', 'Paseo nocturno por la zona de bares', 'Espectáculo cultural o música en vivo', 'Cena romántica con vistas a la ciudad'],
        ];

        $plan  = "## ✈️ Tu aventura en {$city}, {$country}\n\n";
        $plan .= "> *Plan generado automáticamente. ¡Añade tu clave de OpenAI en el servidor para planes personalizados con IA!*\n";
        $plan .= $interestNote . "\n---\n\n";

        for ($d = 1; $d <= $days; $d++) {
            $themes = ['Llegada y primeras impresiones', 'Cultura e historia', 'Naturaleza y barrios locales', 'Gastronomía y mercados', 'Arte y arquitectura', 'Día libre y compras'];
            $theme  = $themes[($d - 1) % count($themes)];
            $plan  .= "### Día {$d} — {$theme}\n\n";
            $plan  .= "**🌅 Mañana:** " . $activities['mañana'][($d - 1) % 4] . " en {$city}.\n\n";
            $plan  .= "**☀️ Tarde:** " . $activities['tarde'][($d - 1) % 4] . ".\n\n";
            $plan  .= "**🌙 Noche:** " . $activities['noche'][($d - 1) % 4] . ".\n\n";
            $plan  .= "---\n\n";
        }

        $plan .= "### 💡 Consejos prácticos\n\n";
        $plan .= "- 🚌 **Transporte:** Usa el transporte público o una tarjeta de transporte de varios días.\n";
        $plan .= "- 💶 **Presupuesto estimado:** Entre 80-150€/día por persona incluyendo alojamiento.\n";
        $plan .= "- 🌤️ **Mejor época:** Primavera (abril-junio) y otoño (sept-noviembre) suelen ser ideales.\n";
        $plan .= "- 📱 **Apps útiles:** Google Maps, Citymapper, TripAdvisor.\n";
        $plan .= "- 🏨 **Alojamiento:** Reserva con antelación en temporada alta.\n";

        return $plan;
    }

    private static function buildLinks(string $city, string $country): array
    {
        $q   = urlencode($city . ' ' . $country);
        $qc  = urlencode($city);
        return [
            'tripadvisor' => "https://www.tripadvisor.es/Search?q={$q}",
            'trivago'     => "https://www.trivago.es/?query={$q}",
            'booking'     => "https://www.booking.com/search.es.html?ss={$q}",
            'airbnb'      => "https://www.airbnb.es/s/{$qc}/homes",
            'skyscanner'  => "https://www.skyscanner.es/transport/flights/mad/{$qc}/",
            'google_maps' => "https://www.google.com/maps/search/{$q}",
        ];
    }
}
