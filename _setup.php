<?php
// One-time schema installer. Delete this file after running.
require_once __DIR__ . '/config/database.php';

$secret = $_GET['key'] ?? '';
if ($secret !== 'setup-bdc-2026') {
    http_response_code(403);
    die('Forbidden');
}

$db = get_db();

$statements = [
    "CREATE TABLE IF NOT EXISTS `users` (
      `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `email`         VARCHAR(255) NOT NULL,
      `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
      `password_hash` VARCHAR(255) NOT NULL,
      `role`          ENUM('admin','viewer') NOT NULL DEFAULT 'admin',
      `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `campaigns` (
      `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `name`             VARCHAR(255) NOT NULL,
      `client_context`   TEXT,
      `public_token`     VARCHAR(64) NOT NULL,
      `status`           ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
      `intro_copy`       TEXT,
      `signature_prompt` VARCHAR(500),
      `created_by`       INT UNSIGNED NOT NULL,
      `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_token` (`public_token`),
      CONSTRAINT `fk_campaigns_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `campaign_slots` (
      `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `campaign_id` INT UNSIGNED NOT NULL,
      `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
      `label`       VARCHAR(255) NOT NULL,
      `prompt_text` TEXT,
      `max_seconds` SMALLINT UNSIGNED DEFAULT NULL,
      `required`    TINYINT(1) NOT NULL DEFAULT 1,
      `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_slots_campaign` (`campaign_id`),
      CONSTRAINT `fk_slots_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `campaign_attestations` (
      `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `campaign_id` INT UNSIGNED NOT NULL,
      `sort_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
      `text`        TEXT NOT NULL,
      `required`    TINYINT(1) NOT NULL DEFAULT 1,
      `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_att_campaign` (`campaign_id`),
      CONSTRAINT `fk_att_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `submissions` (
      `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `campaign_id`           INT UNSIGNED NOT NULL,
      `public_token_snapshot` VARCHAR(64) NOT NULL,
      `dealer_name`           VARCHAR(255) NOT NULL,
      `dealership`            VARCHAR(255) NOT NULL,
      `email`                 VARCHAR(255) NOT NULL,
      `phone`                 VARCHAR(50) NOT NULL,
      `title`                 VARCHAR(255) NOT NULL,
      `signature_text`        VARCHAR(500) NOT NULL,
      `signed_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `ip_address`            VARCHAR(45) NOT NULL,
      `user_agent`            TEXT,
      `started_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `submitted_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `status`                ENUM('pending','reviewed','archived') NOT NULL DEFAULT 'pending',
      `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_sub_campaign` (`campaign_id`),
      CONSTRAINT `fk_sub_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `submission_recordings` (
      `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `submission_id`     INT UNSIGNED NOT NULL,
      `slot_id`           INT UNSIGNED NOT NULL,
      `file_path`         VARCHAR(500) NOT NULL,
      `original_filename` VARCHAR(255) NOT NULL,
      `duration_seconds`  FLOAT DEFAULT NULL,
      `mime_type`         VARCHAR(100) NOT NULL,
      `size_bytes`        INT UNSIGNED NOT NULL,
      `sha256`            VARCHAR(64) NOT NULL,
      `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_rec_submission` (`submission_id`),
      KEY `idx_rec_slot` (`slot_id`),
      CONSTRAINT `fk_rec_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_rec_slot` FOREIGN KEY (`slot_id`) REFERENCES `campaign_slots` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `submission_attestations` (
      `id`                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `submission_id`             INT UNSIGNED NOT NULL,
      `attestation_id`            INT UNSIGNED NOT NULL,
      `attestation_text_snapshot` TEXT NOT NULL,
      `checked_at`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `created_at`                TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_sa_submission` (`submission_id`),
      KEY `idx_sa_attestation` (`attestation_id`),
      CONSTRAINT `fk_sa_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_sa_attestation` FOREIGN KEY (`attestation_id`) REFERENCES `campaign_attestations` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `audit_events` (
      `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `submission_id` INT UNSIGNED DEFAULT NULL,
      `campaign_id`   INT UNSIGNED DEFAULT NULL,
      `event_type`    VARCHAR(100) NOT NULL,
      `payload_json`  JSON DEFAULT NULL,
      `ip_address`    VARCHAR(45) NOT NULL,
      `user_agent`    TEXT,
      `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_audit_submission` (`submission_id`),
      KEY `idx_audit_campaign` (`campaign_id`),
      CONSTRAINT `fk_audit_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE SET NULL,
      CONSTRAINT `fk_audit_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "INSERT IGNORE INTO `users` (`email`, `password_hash`, `role`) VALUES
     ('admin@example.com', '\$2y\$10\$Feg1lKgcgmlpeteJ36TyoeAcN3jXYNx6thbp/C7pRM.tKbQRITy6m', 'admin')",
];

$errors = [];
foreach ($statements as $sql) {
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

if (empty($errors)) {
    echo '<h2>Setup complete.</h2><p>All tables created and admin user seeded. <strong>Delete _setup.php now.</strong></p>';
} else {
    echo '<h2>Completed with errors:</h2><ul>';
    foreach ($errors as $err) {
        echo '<li>' . htmlspecialchars($err) . '</li>';
    }
    echo '</ul>';
}
