<?php
// index.php
require_once __DIR__ . '/config/auth.php';

if (is_logged_in()) {
    if ($_SESSION['ruolo'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: agente.php");
    }
    exit();
} else {
    header("Location: login.php");
    exit();
}
