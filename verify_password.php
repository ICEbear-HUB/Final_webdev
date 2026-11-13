<?php

$password = 'YOUR_NEW_PASSWORD';
$hash = 'YOUR_STORED_PASSWORD_HASH';

var_dump(password_verify($password, $hash));