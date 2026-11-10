<?php
require 'db.php';
$conn->query("ALTER TABLE user MODIFY COLUMN role ENUM('admin', 'adviser', 'student', 'panel') NOT NULL;");
$conn->query("CREATE TABLE IF NOT EXISTS panel_profile (user_id INT PRIMARY KEY, department VARCHAR(100), expertise VARCHAR(255));");
$conn->query("CREATE TABLE IF NOT EXISTS thesis_panel (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, panel_id INT NOT NULL, grade FLOAT DEFAULT NULL, feedback TEXT, status ENUM('assigned', 'graded') DEFAULT 'assigned');");
echo "Done";
?>
