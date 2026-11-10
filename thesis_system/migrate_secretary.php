<?php
require 'db.php';
$res = $conn->query("ALTER TABLE `user` MODIFY COLUMN `role` ENUM('student', 'adviser', 'admin', 'panel', 'secretary') NOT NULL DEFAULT 'student'");
if(!$res) echo "Role alter error: " . $conn->error . "\n";
else echo "Role altered.\n";

$sql = "CREATE TABLE IF NOT EXISTS `secretary_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL UNIQUE,
  `department` varchar(100) DEFAULT 'CEIT Secretariat',
  `office` varchar(150) DEFAULT 'CEIT Defense Secretariat Office',
  `phone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if(!$conn->query($sql)) echo "Table create error: " . $conn->error . "\n";
else echo "secretary_profile table created successfully.\n";

$check_sec = $conn->query("SELECT id FROM `user` WHERE `role` = 'secretary' LIMIT 1");
if ($check_sec && $check_sec->num_rows == 0) {
    $sec_email = 'secretary@csucc.edu.ph';
    $chk_u = $conn->query("SELECT id FROM `user` WHERE `email` = '$sec_email'");
    if (!$chk_u || $chk_u->num_rows == 0) {
        $sec_pass = password_hash('password123', PASSWORD_BCRYPT);
        $conn->query("INSERT INTO `user` (firstname, lastname, sex, dob, name, email, phone, address, password, role, account_status) 
                      VALUES ('Maria', 'Santos', 'Female', '1995-05-15', 'Maria Santos', '$sec_email', '09123456780', 'CSU Cabadbaran Campus', '$sec_pass', 'secretary', 'active')");
        $new_sec_id = $conn->insert_id;
        $conn->query("INSERT INTO `secretary_profile` (user_id, department, office) VALUES ($new_sec_id, 'CEIT Secretariat', 'CEIT Defense Secretariat Office')");
        echo "Inserted demo secretary user (ID: $new_sec_id)\n";
    }
} else {
    $sec = $check_sec->fetch_assoc();
    echo "Secretary already exists with ID: " . $sec['id'] . "\n";
}
