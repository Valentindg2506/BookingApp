<?php
/**
 * webhook.php
 * Receptor del webhook de Meta (Facebook) Lead Ads.
 *
 * GET  → Verificación del endpoint (handshake de Meta)
 * POST → Recepción de nuevos leads
 */

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/includes/settings.php";

define("WEBHOOK_LOG", __DIR__ . "/webhook_log.txt");

// Cargar settings desde BD
try {
    $pdo = Database::getInstance()->getConnection();
    Settings::load($pdo);
} catch (Exception $e) {
    /* continuar con constantes */
}

// ---- Credenciales Meta con fallback a Settings ----
$verifyToken =
    class_exists("Settings") && Settings::get("meta_verify_token") !== ""
        ? Settings::get("meta_verify_token")
        : META_VERIFY_TOKEN;
$appSecret =
    class_exists("Settings") && Settings::get("meta_app_secret") !== ""
        ? Settings::get("meta_app_secret")
        : META_APP_SECRET;
$pageToken =
    class_exists("Settings") && Settings::get("meta_page_access_token") !== ""
        ? Settings::get("meta_page_access_token")
        : META_PAGE_ACCESS_TOKEN;

// -------------------------------------------------------
// Logging helper
// -------------------------------------------------------
function wLog(string $msg): void
{
    $line = "[" . date("Y-m-d H:i:s") . "] " . $msg . PHP_EOL;
    file_put_contents(WEBHOOK_LOG, $line, FILE_APPEND | LOCK_EX);
}

// -------------------------------------------------------
// GET: Verificación del webhook por Meta
// -------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $mode = $_GET["hub_mode"] ?? "";
    $token = $_GET["hub_verify_token"] ?? "";
    $challenge = $_GET["hub_challenge"] ?? "";

    if ($mode === "subscribe" && $token === $verifyToken) {
        wLog("Webhook verificado correctamente.");
        http_response_code(200);
        echo htmlspecialchars($challenge);
    } else {
        wLog("Verificación fallida. Token: " . $token);
        http_response_code(403);
        echo "Forbidden";
    }
    exit();
}

// -------------------------------------------------------
// POST: Recepción de lead data
// -------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rawBody = file_get_contents("php://input");

    // Verificar firma HMAC-SHA256
    $signature = $_SERVER["HTTP_X_HUB_SIGNATURE_256"] ?? "";
    if (!verifySignature($rawBody, $signature, $appSecret)) {
        wLog("Firma HMAC inválida. Petición rechazada.");
        http_response_code(403);
        echo "Invalid signature";
        exit();
    }

    $payload = json_decode($rawBody, true);
    if (!$payload || !isset($payload["entry"])) {
        wLog("Payload inválido o vacío.");
        http_response_code(200); // Meta espera 200 aunque ignoremos
        echo "OK";
        exit();
    }

    $pdo = Database::getInstance()->getConnection();

    foreach ($payload["entry"] as $entry) {
        foreach ($entry["changes"] ?? [] as $change) {
            if (($change["field"] ?? "") !== "leadgen") {
                continue;
            }

            $value = $change["value"] ?? [];
            $leadId = $value["leadgen_id"] ?? null;

            if (!$leadId) {
                continue;
            }

            wLog(
                "Lead recibido: " .
                    $leadId .
                    " | ad_id=" .
                    ($value["ad_id"] ?? "-"),
            );

            // Extraer field_data si viene en el payload
            $fieldData = $value["field_data"] ?? [];
            $parsedData = parseFieldData($fieldData);

            // Si no vienen los datos completos en el webhook,
            // habría que hacer una llamada a Graph API para obtenerlos.
            // Por ahora guardamos lo que llega.
            $fullName =
                $parsedData["full_name"] ??
                trim(
                    ($parsedData["first_name"] ?? "") .
                        " " .
                        ($parsedData["last_name"] ?? ""),
                ) ?:
                "Desconocido";
            $email = $parsedData["email"] ?? null;
            $phone =
                $parsedData["phone_number"] ?? ($parsedData["phone"] ?? null);

            try {
                $stmt = $pdo->prepare(
                    "INSERT IGNORE INTO leads
                        (meta_lead_id, full_name, email, phone,
                         ad_id, adset_id, campaign_id, form_id, raw_data)
                     VALUES
                        (:meta_lead_id, :full_name, :email, :phone,
                         :ad_id, :adset_id, :campaign_id, :form_id, :raw_data)",
                );
                $stmt->execute([
                    ":meta_lead_id" => $leadId,
                    ":full_name" => $fullName,
                    ":email" => $email,
                    ":phone" => $phone,
                    ":ad_id" => $value["ad_id"] ?? null,
                    ":adset_id" => $value["adset_id"] ?? null,
                    ":campaign_id" => $value["campaign_id"] ?? null,
                    ":form_id" => $value["form_id"] ?? null,
                    ":raw_data" => json_encode($value, JSON_UNESCAPED_UNICODE),
                ]);
                wLog(
                    "Lead guardado en BD: " .
                        $leadId .
                        " | nombre=" .
                        $fullName,
                );
            } catch (PDOException $e) {
                wLog(
                    "ERROR BD al guardar lead " .
                        $leadId .
                        ": " .
                        $e->getMessage(),
                );
            }
        }
    }

    http_response_code(200);
    echo "OK";
    exit();
}

// Cualquier otro método
http_response_code(405);
echo "Method Not Allowed";

// -------------------------------------------------------
// Verifica la firma HMAC-SHA256 de Meta
// -------------------------------------------------------
function verifySignature(
    string $body,
    string $signature,
    string $secret = "",
): bool {
    $appSecret =
        $secret !== ""
            ? $secret
            : (defined("META_APP_SECRET")
                ? META_APP_SECRET
                : "");
    if (empty($signature) || empty($appSecret)) {
        return false;
    }

    // Formato: sha256=XXXXXXX
    if (!str_starts_with($signature, "sha256=")) {
        return false;
    }

    $received = substr($signature, 7);
    $expected = hash_hmac("sha256", $body, $appSecret);

    return hash_equals($expected, $received);
}

// -------------------------------------------------------
// Parsea el array field_data de Meta a un array asociativo
// -------------------------------------------------------
function parseFieldData(array $fieldData): array
{
    $result = [];
    foreach ($fieldData as $field) {
        $key = strtolower(str_replace(" ", "_", $field["name"] ?? ""));
        $result[$key] = $field["values"][0] ?? null;
    }
    return $result;
}
