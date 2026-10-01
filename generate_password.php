<?php

// Simple script to generate password hash
// Usage: Enter a password below and this will generate the bcrypt hash

$password = "Admin@123";  // Change this to your desired password

$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br><br>";

echo "SQL Query to update admin:<br>";
echo "UPDATE users SET password_hash = '" . $hash . "' WHERE email = 'admin@myelimu.com';";

?>
