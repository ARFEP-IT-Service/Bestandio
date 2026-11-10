-- Bestandio: initial schema

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(64)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locations (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_locations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE devices (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventory_no   VARCHAR(64)  NOT NULL,
    category_id    INT UNSIGNED NOT NULL,
    manufacturer   VARCHAR(100) NOT NULL DEFAULT '',
    model          VARCHAR(100) NOT NULL DEFAULT '',
    serial_no      VARCHAR(100) NOT NULL DEFAULT '',
    location_id    INT UNSIGNED NOT NULL,
    status         ENUM('in_use','stock','repair','retired') NOT NULL DEFAULT 'stock',
    purchase_date  DATE NULL,
    warranty_until DATE NULL,
    note           TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_devices_inventory_no (inventory_no),
    KEY ix_devices_serial_no (serial_no),
    KEY ix_devices_status (status),
    CONSTRAINT fk_devices_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_devices_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_history (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NULL,
    changed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    field      VARCHAR(32)  NOT NULL,
    old_value  TEXT NULL,
    new_value  TEXT NULL,
    PRIMARY KEY (id),
    KEY ix_history_device (device_id, changed_at),
    CONSTRAINT fk_history_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username     VARCHAR(64) NOT NULL,
    ip           VARCHAR(45) NOT NULL,
    attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_attempts_username (username, attempted_at),
    KEY ix_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
