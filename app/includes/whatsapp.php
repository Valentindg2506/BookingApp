<?php

/**
 * whatsapp.php
 *
 * Clase WhatsApp: envío de mensajes a través de la API REST de Twilio.
 * Incluye métodos helper para los mensajes predefinidos de la aplicación
 * (confirmación de reserva, recordatorio 1 día antes, recordatorio 2 horas antes).
 */

if (!defined("TWILIO_ACCOUNT_SID")) {
    require_once __DIR__ . "/../config.php";
}

class WhatsApp
{
    // --------------------------------------------------------
    //  Constantes internas
    // --------------------------------------------------------
    private const API_BASE = "https://api.twilio.com/2010-04-01/Accounts";

    // --------------------------------------------------------
    //  Envío genérico
    // --------------------------------------------------------

    /**
     * Envía un mensaje de WhatsApp a través de Twilio.
     *
     * @param  string $to      Destinatario en formato "whatsapp:+XXXXXXXXXXX"
     * @param  string $message Cuerpo del mensaje (texto plano, soporta emojis).
     * @return array{success: bool, sid: string|null, error: string|null}
     */
    public static function send(string $to, string $message): array
    {
        $accountSid =
            class_exists("Settings") &&
            Settings::get("twilio_account_sid") !== ""
                ? Settings::get("twilio_account_sid")
                : TWILIO_ACCOUNT_SID;
        $authToken =
            class_exists("Settings") &&
            Settings::get("twilio_auth_token") !== ""
                ? Settings::get("twilio_auth_token")
                : TWILIO_AUTH_TOKEN;
        $from =
            class_exists("Settings") &&
            Settings::get("twilio_whatsapp_from") !== ""
                ? Settings::get("twilio_whatsapp_from")
                : TWILIO_WHATSAPP_FROM;

        $url = sprintf("%s/%s/Messages.json", self::API_BASE, $accountSid);

        $postFields = http_build_query([
            "From" => $from,
            "To" => $to,
            "Body" => $message,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $accountSid . ":" . $authToken,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded",
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Error de red / cURL
        if ($response === false) {
            error_log("[WhatsApp] cURL error: " . $curlError);
            return [
                "success" => false,
                "sid" => null,
                "error" => "Error de red: " . $curlError,
            ];
        }

        $data = json_decode($response, true);

        // Error HTTP de Twilio
        if ($httpCode < 200 || $httpCode >= 300) {
            $errorMsg = $data["message"] ?? "HTTP " . $httpCode;
            error_log(
                "[WhatsApp] Twilio error (" . $httpCode . "): " . $errorMsg,
            );
            return [
                "success" => false,
                "sid" => null,
                "error" => $errorMsg,
            ];
        }

        return [
            "success" => true,
            "sid" => $data["sid"] ?? null,
            "error" => null,
        ];
    }

    // --------------------------------------------------------
    //  Mensajes predefinidos
    // --------------------------------------------------------

    /**
     * Envía la confirmación de reserva al cliente.
     *
     * @param  string $phone        Número del cliente (sin prefijo "whatsapp:").
     * @param  string $name         Nombre del cliente.
     * @param  string $date         Fecha de la cita (formato Y-m-d o d/m/Y).
     * @param  string $time         Hora de la cita (formato H:i o H:i:s).
     * @param  string $businessName Nombre del negocio.
     * @return array{success: bool, sid: string|null, error: string|null}
     */
    public static function sendConfirmation(
        string $phone,
        string $name,
        string $date,
        string $time,
        string $businessName,
    ): array {
        $to = self::normalizeToWhatsApp($phone);

        $message =
            "✅ *¡Reserva confirmada!*\n\n" .
            "Hola {$name}, tu cita ha sido confirmada con éxito. 🎉\n\n" .
            "📅 *Fecha:* {$date}\n" .
            "🕐 *Hora:* {$time}\n" .
            "🏢 *Lugar:* {$businessName}\n\n" .
            "Si necesitas cancelar o modificar tu cita, por favor contáctanos con antelación.\n\n" .
            "¡Te esperamos! 😊";

        return self::send($to, $message);
    }

    /**
     * Envía una notificación de cancelación de cita al cliente.
     *
     * @param  string $phone        Número del cliente.
     * @param  string $name         Nombre del cliente.
     * @param  string $date         Fecha de la cita.
     * @param  string $time         Hora de la cita.
     * @param  string $businessName Nombre del negocio.
     * @param  string $businessPhone Teléfono de contacto del negocio.
     * @return array{success: bool, sid: string|null, error: string|null}
     */
    public static function sendCancellation(
        string $phone,
        string $name,
        string $date,
        string $time,
        string $businessName,
        string $businessPhone = "",
    ): array {
        $to = self::normalizeToWhatsApp($phone);

        $contactLine = !empty($businessPhone)
            ? "Si deseas reagendar o tienes alguna consulta, no dudes en contactarnos al {$businessPhone}.\n\n"
            : "Si deseas reagendar o tienes alguna consulta, no dudes en contactarnos.\n\n";

        $message =
            "❌ *Cita cancelada*\n\n" .
            "Hola {$name}, te informamos que tu cita ha sido cancelada. Lamentamos los inconvenientes que esto pueda ocasionarte.\n\n" .
            "📅 *Fecha:* {$date}\n" .
            "🕐 *Hora:* {$time}\n" .
            "🏢 *{$businessName}*\n\n" .
            $contactLine .
            "Gracias por tu comprensión. 🙏";

        return self::send($to, $message);
    }

    /**
     * Envía un recordatorio 1 día antes de la cita.
     *
     * @param  string $phone        Número del cliente.
     * @param  string $name         Nombre del cliente.
     * @param  string $date         Fecha de la cita.
     * @param  string $time         Hora de la cita.
     * @param  string $businessName Nombre del negocio.
     * @return array{success: bool, sid: string|null, error: string|null}
     */
    public static function sendReminder1Day(
        string $phone,
        string $name,
        string $date,
        string $time,
        string $businessName,
    ): array {
        $to = self::normalizeToWhatsApp($phone);

        $message =
            "🔔 *Recordatorio de cita*\n\n" .
            "Hola {$name}, te recordamos que *mañana* tienes una cita con nosotros. 📆\n\n" .
            "📅 *Fecha:* {$date}\n" .
            "🕐 *Hora:* {$time}\n" .
            "🏢 *{$businessName}*\n\n" .
            "Por favor, llega unos minutos antes para que podamos atenderte con puntualidad. ⏰\n\n" .
            "Si necesitas cancelar, avísanos con la mayor antelación posible. ¡Gracias! 🙏";

        return self::send($to, $message);
    }

    /**
     * Envía un recordatorio 1 hora antes de la cita con el enlace de Google Meet.
     *
     * @param  string $phone        Número del cliente.
     * @param  string $name         Nombre del cliente.
     * @param  string $date         Fecha de la cita.
     * @param  string $time         Hora de la cita.
     * @param  string $businessName Nombre del negocio.
     * @param  string $meetLink     Enlace de Google Meet.
     * @return array{success: bool, sid: string|null, error: string|null}
     */
    public static function sendReminder1Hour(
        string $phone,
        string $name,
        string $date,
        string $time,
        string $businessName,
        string $meetLink,
    ): array {
        $to = self::normalizeToWhatsApp($phone);

        $message =
            "⏰ *¡Tu cita es en 1 hora!*\n\n" .
            "Hola {$name}, en breve comenzará tu reunión con *{$businessName}*. 🚀\n\n" .
            "📅 *Fecha:* {$date}\n" .
            "🕐 *Hora:* {$time}\n\n" .
            "🎥 *Accede a la videollamada aquí:*\n" .
            "{$meetLink}\n\n" .
            "Asegúrate de tener buena conexión y un lugar tranquilo. 💻\n\n" .
            "¡Nos vemos pronto! 👋";

        return self::send($to, $message);
    }

    public static function sendReminder15Min(
        string $phone,
        string $name,
        string $time,
        string $businessName,
        string $meetLink,
    ): array {
        $to = self::normalizeToWhatsApp($phone);

        $message =
            "🔴 *¡Tu reunión comienza en 15 minutos!*\n\n" .
            "Hola {$name}, tu cita con *{$businessName}* empieza a las *{$time}*.\n\n" .
            "🎥 *Únete ahora:*\n" .
            "{$meetLink}\n\n" .
            "¡Te esperamos! 💼";

        return self::send($to, $message);
    }

    // --------------------------------------------------------
    //  Utilidades privadas
    // --------------------------------------------------------

    /**
     * Asegura que el número lleve el prefijo "whatsapp:" que requiere Twilio.
     *
     * @param  string $phone
     * @return string
     */
    private static function normalizeToWhatsApp(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, "whatsapp:")) {
            return $phone;
        }

        // Asegurarse de que tenga + para el formato internacional
        if (!str_starts_with($phone, "+")) {
            // Si es número español sin prefijo (9 dígitos, empieza por 6, 7 o 9)
            if (preg_match('/^[679]\d{8}$/', $phone)) {
                $phone = "+34" . $phone;
            } else {
                $phone = "+" . $phone;
            }
        }

        return "whatsapp:" . $phone;
    }
}
