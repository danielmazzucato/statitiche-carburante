<?php
// logout.php
require_once __DIR__ . '/config/auth.php';

// Azzera tutte le variabili di sessione
$_SESSION = array();

// Se desideri distruggere completamente la sessione, cancella anche i cookie di sessione.
// Nota: Questo distruggerà la sessione e non solo i dati della sessione!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Infine, distruggi la sessione.
session_destroy();

// Reindirizza al login
header("Location: login.php");
exit();
