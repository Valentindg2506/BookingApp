-- ============================================================
--  BookingApp - Schema de base de datos
--  Versión: 1.0
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------
--  Tabla: admin_users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(100)    NOT NULL,
    `password`   VARCHAR(255)    NOT NULL,
    `email`      VARCHAR(255)    NOT NULL,
    `name`       VARCHAR(255)    NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_username` (`username`),
    UNIQUE KEY `uq_email`    (`email`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios administradores del panel';

-- ------------------------------------------------------------
--  Tabla: leads
--  Almacena los leads recibidos desde Meta / Facebook Ads
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `meta_lead_id` VARCHAR(255)    DEFAULT NULL,
    `full_name`    VARCHAR(255)    NOT NULL,
    `email`        VARCHAR(255)    DEFAULT NULL,
    `phone`        VARCHAR(50)     DEFAULT NULL,
    `ad_id`        VARCHAR(255)    DEFAULT NULL,
    `adset_id`     VARCHAR(255)    DEFAULT NULL,
    `campaign_id`  VARCHAR(255)    DEFAULT NULL,
    `form_id`      VARCHAR(255)    DEFAULT NULL,
    `raw_data`     TEXT            DEFAULT NULL COMMENT 'JSON raw del webhook de Meta',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_meta_lead_id` (`meta_lead_id`),
    KEY `idx_email`   (`email`),
    KEY `idx_phone`   (`phone`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Leads recibidos desde Facebook/Meta Ads';

-- ------------------------------------------------------------
--  Tabla: appointments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appointments` (
    `id`                    INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    `lead_id`               INT UNSIGNED        DEFAULT NULL COMMENT 'FK opcional a leads',
    `full_name`             VARCHAR(255)        NOT NULL,
    `email`                 VARCHAR(255)        NOT NULL,
    `phone`                 VARCHAR(50)         NOT NULL,
    `appointment_date`      DATE                NOT NULL,
    `appointment_time`      TIME                NOT NULL,
    `meet_link`             VARCHAR(500)        DEFAULT NULL,
    `status`                ENUM(
                                'pending',
                                'confirmed',
                                'cancelled',
                                'completed'
                            )                   NOT NULL DEFAULT 'pending',
    `notes`                 TEXT                DEFAULT NULL,
    `reminder_1day_sent`    TINYINT(1)          NOT NULL DEFAULT 0,
    `reminder_2hours_sent`  TINYINT(1)          NOT NULL DEFAULT 0,
    `confirmation_sent`     TINYINT(1)          NOT NULL DEFAULT 0,
    `created_at`            DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_id`   (`lead_id`),
    KEY `idx_date`      (`appointment_date`),
    KEY `idx_status`    (`status`),
    KEY `idx_phone`     (`phone`),
    CONSTRAINT `fk_appointment_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Citas / reservas';

-- ------------------------------------------------------------
--  Tabla: availability_schedule
--  Horario recurrente por día de la semana
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `availability_schedule` (
    `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `day_of_week`            TINYINT       NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes, 6=Sábado',
    `start_time`             TIME          NOT NULL,
    `end_time`               TIME          NOT NULL,
    `slot_duration_minutes`  INT           NOT NULL DEFAULT 30,
    `is_active`              TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_day` (`day_of_week`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Horario de disponibilidad recurrente por día';

-- ------------------------------------------------------------
--  Tabla: availability_exceptions
--  Excepciones puntuales: bloqueos o días especiales
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `availability_exceptions` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `exception_date` DATE          NOT NULL,
    `is_blocked`     TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1=día bloqueado, 0=día extra disponible',
    `reason`         VARCHAR(255)  DEFAULT NULL,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_exception_date` (`exception_date`),
    KEY `idx_blocked` (`is_blocked`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Excepciones puntuales al horario: festivos, días especiales, etc.';

-- ------------------------------------------------------------
--  Tabla: notification_logs
--  Registro de notificaciones WhatsApp enviadas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification_logs` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `appointment_id`  INT UNSIGNED    NOT NULL,
    `type`            ENUM(
                          'confirmation',
                          'reminder_1day',
                          'reminder_2hours'
                      )               NOT NULL,
    `whatsapp_to`     VARCHAR(50)     NOT NULL,
    `message_sid`     VARCHAR(255)    DEFAULT NULL COMMENT 'SID retornado por Twilio',
    `status`          ENUM(
                          'pending',
                          'sent',
                          'failed'
                      )               NOT NULL DEFAULT 'pending',
    `error_message`   TEXT            DEFAULT NULL,
    `sent_at`         TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_appointment` (`appointment_id`),
    KEY `idx_type`        (`type`),
    KEY `idx_status`      (`status`),
    CONSTRAINT `fk_notif_appointment`
        FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de notificaciones WhatsApp vía Twilio';

-- ============================================================
--  Datos iniciales
-- ============================================================

-- Admin por defecto
--   usuario:    admin
--   contraseña: admin123
--   hash generado con password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `admin_users` (`username`, `password`, `email`, `name`)
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uJadRuJGi',
    'admin@tudominio.com',
    'Administrador'
);

-- Disponibilidad por defecto: Lunes a Viernes, 09:00-18:00, slots de 30 min
-- day_of_week: 1=Lunes, 2=Martes, 3=Miércoles, 4=Jueves, 5=Viernes
INSERT INTO `availability_schedule`
    (`day_of_week`, `start_time`, `end_time`, `slot_duration_minutes`, `is_active`)
VALUES
    (1, '09:00:00', '18:00:00', 30, 1),  -- Lunes
    (2, '09:00:00', '18:00:00', 30, 1),  -- Martes
    (3, '09:00:00', '18:00:00', 30, 1),  -- Miércoles
    (4, '09:00:00', '18:00:00', 30, 1),  -- Jueves
    (5, '09:00:00', '18:00:00', 30, 1);  -- Viernes

SET foreign_key_checks = 1;
