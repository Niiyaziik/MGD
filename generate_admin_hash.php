<?php
// generate_admin_hash.php

$password = 'pass'; // тут придумываешь свой пароль

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Хеш для пароля '{$password}':\n";
echo $hash . "\n";
