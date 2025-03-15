<?php
// Generate a secure random key
$key = bin2hex(random_bytes(32));
echo "Your JWT Secret Key: " . $key . "\n";
?>
