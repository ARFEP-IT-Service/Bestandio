-- Bestandio: SMBIOS system UUID from scans, second matching key after manufacturer + serial number.
-- Not unique: cloned machines can share a UUID.

ALTER TABLE devices
    ADD COLUMN uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL AFTER scanned_at,
    ADD KEY ix_devices_uuid (uuid);
