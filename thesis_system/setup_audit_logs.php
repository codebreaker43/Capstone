<?php
require_once __DIR__ . '/db.php';

// SQL to create audit_logs table
$sql = "CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `user_role` VARCHAR(50) NOT NULL DEFAULT 'guest',
    `action` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'System',
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('success', 'failed', 'warning') NOT NULL DEFAULT 'success',
    `details` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_category` (`category`),
    INDEX `idx_user_role` (`user_role`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'audit_logs' verified or created successfully." . PHP_EOL;

    // Check if initial log exists, if not seed a system initialization record
    $check = $conn->query("SELECT id FROM audit_logs LIMIT 1");
    if ($check && $check->num_rows == 0) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $agent = 'System Installer';
        $init_desc = "Audit Logging System successfully initialized and active for CEIT Capstone & Matchmaking Portal.";
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, user_name, user_role, action, category, description, ip_address, user_agent, status) VALUES (NULL, 'System', 'system', 'SYSTEM_INIT', 'System', ?, ?, ?, 'success')");
        $stmt->bind_param("sss", $init_desc, $ip, $agent);
        $stmt->execute();
        echo "Initial audit log entry created." . PHP_EOL;
    }
} else {
    echo "Error creating table: " . $conn->error . PHP_EOL;
}
