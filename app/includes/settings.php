<?php
/**
 * settings.php
 * Clase Settings: lee y escribe configuración desde la tabla app_settings.
 * Hace fallback a las constantes de config.php si la clave no está en BD.
 */

class Settings
{
    private static array $cache = [];
    private static bool  $loaded = false;

    /** Carga todos los settings de BD en caché */
    public static function load(PDO $pdo): void
    {
        if (self::$loaded) return;
        try {
            $stmt = $pdo->query("SELECT `key`, `value` FROM app_settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$cache[$row['key']] = $row['value'];
            }
            self::$loaded = true;
        } catch (Exception $e) {
            error_log('[Settings] Error cargando settings: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene un valor. Prioridad: BD → constante PHP → $default
     */
    public static function get(string $key, string $default = ''): string
    {
        if (isset(self::$cache[$key]) && self::$cache[$key] !== '') {
            return self::$cache[$key];
        }
        // Fallback a constante PHP (ej: 'business_name' → BUSINESS_NAME)
        $const = strtoupper($key);
        if (defined($const)) {
            return (string) constant($const);
        }
        return $default;
    }

    /** Guarda uno o varios settings en BD */
    public static function save(PDO $pdo, array $data): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO app_settings (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()"
        );
        foreach ($data as $key => $value) {
            $stmt->execute([':k' => $key, ':v' => $value]);
            self::$cache[$key] = $value;
        }
    }

    /** Devuelve todos los settings de un grupo */
    public static function getGroup(PDO $pdo, string $group): array
    {
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM app_settings WHERE `group` = :g ORDER BY `key`");
        $stmt->execute([':g' => $group]);
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }
}
