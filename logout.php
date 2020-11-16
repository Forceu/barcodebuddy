<?php

require_once __DIR__ . '/incl/authentication/authentication.inc.php';

$auth->logOut();
header("Location:./login.php");

