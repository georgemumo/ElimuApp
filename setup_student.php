<?php

require_once "db.php";

$password = "Admin@123";
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "=== STUDENT ACCOUNT SETUP ===<br><br>";

echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br><br>";

echo "=== SQL QUERIES TO RUN ===<br><br>";

echo "1. Create a class (if needed):<br>";
echo "<code>INSERT INTO `classes` (class_name, stream_name) VALUES ('Form 4', 'East');</code><br><br>";

echo "2. Create the student user:<br>";
echo "<code>INSERT INTO `users` (email, password_hash, role, is_verified, status)
VALUES (
    'student@myelimu.com',
    '" . $hash . "',
    'STUDENT',
    1,
    'ACTIVE'
);</code><br><br>";

echo "3. Create the student profile:<br>";
echo "<code>INSERT INTO `students` (user_id, adm_number, full_name, gender, date_of_birth, class_id, status)
VALUES (
    (SELECT user_id FROM users WHERE email = 'student@myelimu.com'),
    'STU000001',
    'Admin Student',
    'MALE',
    '2008-01-01',
    (SELECT class_id FROM classes LIMIT 1),
    'ACTIVE'
);</code><br><br>";

echo "4. Create fee account:<br>";
echo "<code>INSERT INTO `fee_accounts` (student_id, total_billed, total_paid, current_balance)
VALUES (
    (SELECT student_id FROM students WHERE adm_number = 'STU000001'),
    0,
    0,
    0
);</code><br><br>";

echo "=== LOGIN DETAILS ===<br>";
echo "Email: student@myelimu.com<br>";
echo "Password: Admin@123<br>";

?>
