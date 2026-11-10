-- Bestandio: settings maintained by admins (company name, label layout)

CREATE TABLE settings (
    name  VARCHAR(64)  NOT NULL,
    value VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
