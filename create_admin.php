<?php
$password = 'admin123'; // Beddel haddii aad rabto
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Username: admin<br>";
echo "Password: " . $password . "<br>";
echo "Hash: " . $hash;
?>