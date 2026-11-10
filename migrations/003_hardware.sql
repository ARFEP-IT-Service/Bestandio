-- Bestandio: hardware data from scan imports, software list

ALTER TABLE devices
    ADD COLUMN cpu        VARCHAR(190) NOT NULL DEFAULT '' AFTER note,
    ADD COLUMN ram_mb     INT UNSIGNED NULL AFTER cpu,
    ADD COLUMN storage    VARCHAR(500) NOT NULL DEFAULT '' AFTER ram_mb,
    ADD COLUMN os_name    VARCHAR(100) NOT NULL DEFAULT '' AFTER storage,
    ADD COLUMN os_version VARCHAR(64)  NOT NULL DEFAULT '' AFTER os_name,
    ADD COLUMN scanned_at DATETIME NULL AFTER os_version;

CREATE TABLE device_software (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id INT UNSIGNED NOT NULL,
    name      VARCHAR(190) NOT NULL,
    version   VARCHAR(64)  NOT NULL DEFAULT '',
    vendor    VARCHAR(190) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY ix_software_device (device_id),
    CONSTRAINT fk_software_device FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
