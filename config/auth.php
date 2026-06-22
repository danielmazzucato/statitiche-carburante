<?php
// config/auth.php
require_once __DIR__ . '/database.php';

function sec_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Parametri per una sessione sicura
        $session_name = 'sec_session_carburante';
        $secure = false; // Impostare su true se si usa HTTPS
        $httponly = true;
        $samesite = 'Lax';

        // Impedisce a JavaScript di leggere la sessione
        if (ini_get('session.use_only_cookies') === '0') {
            ini_set('session.use_only_cookies', '1');
        }

        // Recupera i parametri correnti dei cookie di sessione
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $cookieParams["lifetime"],
            'path' => $cookieParams["path"],
            'domain' => $cookieParams["domain"],
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);

        session_name($session_name);
        session_start();
    }
}

// Avvia sempre la sessione sicura
sec_session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT u.id, u.username, u.nome_completo, u.ruolo, u.nazionalita, u.creato_il, a.modello AS modello_auto, a.consumo_medio, a.targa FROM utenti u LEFT JOIN auto a ON u.id = a.utente_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
}

function require_role(string $role) {
    require_login();
    if ($_SESSION['ruolo'] !== $role) {
        // Se l'agente prova ad andare sull'admin o viceversa, lo rimanda alla sua home
        if ($_SESSION['ruolo'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: agente.php");
        }
        exit();
    }
}
