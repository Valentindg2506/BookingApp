<?php

/**
 * google_meet.php
 *
 * Clase GoogleMeet: crea eventos con Google Meet en Google Calendar
 * usando OAuth 2.0 (flujo Authorization Code con refresh_token).
 * Toda la comunicación con la API se realiza a través de cURL,
 * sin dependencias externas adicionales.
 */

if (!defined('GOOGLE_CLIENT_ID')) {
    require_once __DIR__ . '/../config.php';
}

class GoogleMeet
{
    // --------------------------------------------------------
    //  Constantes de la API de Google
    // --------------------------------------------------------
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_URL = 'https://www.googleapis.com/calendar/v3/calendars';

    // --------------------------------------------------------
    //  Gestión de tokens OAuth 2.0
    // --------------------------------------------------------

    /**
     * Obtiene un access token válido.
     * Lee los tokens del fichero local; si el access token ha expirado,
     * usa el refresh_token para obtener uno nuevo y lo guarda.
     *
     * @return string|null  Access token o null si no hay tokens configurados.
     */
    public function getAccessToken(): ?string
    {
        $tokensFile = GOOGLE_TOKENS_FILE;

        if (!file_exists($tokensFile)) {
            error_log('[GoogleMeet] Fichero de tokens no encontrado: ' . $tokensFile);
            return null;
        }

        $json   = file_get_contents($tokensFile);
        $tokens = json_decode($json, true);

        if (empty($tokens['access_token'])) {
            error_log('[GoogleMeet] access_token no encontrado en el fichero de tokens.');
            return null;
        }

        // Comprobar si el access token ha expirado (con 60 s de margen)
        $expiresAt = $tokens['expires_at'] ?? 0;
        if (time() >= ($expiresAt - 60)) {
            if (empty($tokens['refresh_token'])) {
                error_log('[GoogleMeet] Access token expirado y no hay refresh_token.');
                return null;
            }

            $newTokens = $this->refreshAccessToken($tokens['refresh_token']);
            if ($newTokens === null) {
                return null;
            }

            // Combinar tokens actualizados y guardar
            $tokens = array_merge($tokens, $newTokens);
            $this->saveTokens($tokens);
        }

        return $tokens['access_token'];
    }

    /**
     * Refresca el access token usando el refresh_token.
     *
     * @param  string $refreshToken
     * @return array<string,mixed>|null  Array con los nuevos tokens o null en caso de error.
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        $postFields = http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::TOKEN_URL,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('[GoogleMeet] cURL error al refrescar token: ' . $curlError);
            return null;
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || empty($data['access_token'])) {
            error_log('[GoogleMeet] Error al refrescar token (' . $httpCode . '): ' . $response);
            return null;
        }

        // Calcular el timestamp de expiración
        $data['expires_at'] = time() + (int) ($data['expires_in'] ?? 3600);

        return $data;
    }

    // --------------------------------------------------------
    //  Creación de reuniones
    // --------------------------------------------------------

    /**
     * Crea un evento en Google Calendar con conferencia Google Meet adjunta.
     *
     * @param  string      $summary          Título del evento.
     * @param  string      $description      Descripción del evento.
     * @param  string      $dateTime         Fecha y hora de inicio en formato ISO 8601 (p.ej. "2025-06-15T10:00:00").
     * @param  int         $durationMinutes  Duración en minutos (por defecto 60).
     * @param  string|null $attendeeEmail    Email del asistente (opcional).
     * @return string  Enlace de Google Meet (o enlace de placeholder si hay error).
     */
    public function createMeeting(
        string $summary,
        string $description,
        string $dateTime,
        int $durationMinutes = 60,
        ?string $attendeeEmail = null
    ): string {
        $accessToken = $this->getAccessToken();

        if ($accessToken === null) {
            error_log('[GoogleMeet] Sin access token; se usa enlace de placeholder.');
            return $this->generatePlaceholderLink();
        }

        // Calcular hora de fin
        $startTimestamp = strtotime($dateTime);
        if ($startTimestamp === false) {
            error_log('[GoogleMeet] Formato de fecha/hora inválido: ' . $dateTime);
            return $this->generatePlaceholderLink();
        }

        $endTimestamp = $startTimestamp + ($durationMinutes * 60);
        $timezone     = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Europe/Madrid';

        $startIso = date('c', $startTimestamp);  // ISO 8601 con offset de la TZ local
        $endIso   = date('c', $endTimestamp);

        // Construir el cuerpo del evento
        $requestId = substr(md5(uniqid((string) mt_rand(), true)), 0, 16);

        $eventBody = [
            'summary'     => $summary,
            'description' => $description,
            'start'       => [
                'dateTime' => $startIso,
                'timeZone' => $timezone,
            ],
            'end'         => [
                'dateTime' => $endIso,
                'timeZone' => $timezone,
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId'             => $requestId,
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ];

        // Añadir asistente si se proporcionó email
        if ($attendeeEmail !== null && filter_var($attendeeEmail, FILTER_VALIDATE_EMAIL)) {
            $eventBody['attendees'] = [['email' => $attendeeEmail]];
        }

        $calendarId = defined('GOOGLE_CALENDAR_ID') ? GOOGLE_CALENDAR_ID : 'primary';
        $url        = sprintf(
            '%s/%s/events?conferenceDataVersion=1&sendUpdates=none',
            self::CALENDAR_URL,
            urlencode($calendarId)
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($eventBody),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('[GoogleMeet] cURL error al crear evento: ' . $curlError);
            return $this->generatePlaceholderLink();
        }

        $data = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('[GoogleMeet] Error al crear evento (' . $httpCode . '): ' . $response);
            return $this->generatePlaceholderLink();
        }

        // Extraer el hangoutLink del evento creado
        $meetLink = $data['hangoutLink'] ?? null;

        if (empty($meetLink)) {
            // Intentar extraer de conferenceData
            $meetLink = $data['conferenceData']['entryPoints'][0]['uri'] ?? null;
        }

        if (empty($meetLink)) {
            error_log('[GoogleMeet] Evento creado pero sin hangoutLink. Usando placeholder.');
            return $this->generatePlaceholderLink();
        }

        return $meetLink;
    }

    // --------------------------------------------------------
    //  Utilidades privadas
    // --------------------------------------------------------

    /**
     * Guarda el array de tokens en el fichero JSON definido en la configuración.
     *
     * @param  array<string,mixed> $tokens
     * @return void
     */
    private function saveTokens(array $tokens): void
    {
        $tokensFile = GOOGLE_TOKENS_FILE;
        $dir        = dirname($tokensFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents(
            $tokensFile,
            json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    /**
     * Genera un enlace de Google Meet de placeholder cuando no es posible
     * crear uno real a través de la API.
     *
     * Formato: https://meet.google.com/xxx-xxxx-xxx
     *
     * @return string
     */
    private function generatePlaceholderLink(): string
    {
        $hash = substr(md5(uniqid((string) mt_rand(), true)), 0, 11);

        // Formatear como xxx-xxxx-xxx (3-4-3)
        $part1 = substr($hash, 0, 3);
        $part2 = substr($hash, 3, 4);
        $part3 = substr($hash, 7, 3);

        return 'https://meet.google.com/' . $part1 . '-' . $part2 . '-' . $part3;
    }
}
