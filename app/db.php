<?php

require_once __DIR__ . '/config.php';

/**
 * Database
 *
 * Proporciona una única instancia PDO (patrón Singleton) configurada
 * con charset utf8mb4, modo de error EXCEPTION y fetch mode ASSOC.
 */
class Database
{
    /** @var Database|null Instancia única de la clase */
    private static ?Database $instance = null;

    /** @var PDO Conexión PDO activa */
    private PDO $connection;

    /**
     * Constructor privado: crea la conexión PDO.
     *
     * @throws RuntimeException Si la conexión falla.
     */
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // No expongas credenciales en producción; registra en log.
            error_log('[Database] Error de conexión: ' . $e->getMessage());
            throw new RuntimeException(
                'No se pudo conectar a la base de datos. Consulta el log del servidor.'
            );
        }
    }

    /**
     * Evita la clonación de la instancia.
     */
    private function __clone(): void {}

    /**
     * Evita la deserialización de la instancia.
     *
     * @throws RuntimeException Siempre.
     */
    public function __wakeup(): void
    {
        throw new RuntimeException('No se puede deserializar un Singleton.');
    }

    /**
     * Retorna la instancia única de Database.
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retorna la conexión PDO activa.
     *
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
