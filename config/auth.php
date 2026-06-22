<?php
// config/auth.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';

function sec_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Parametri per una sessione ultra-sicura
        $session_name = 'sec_session_carburante';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $httponly = true;
        $samesite = 'Strict'; // Più restrittivo di 'Lax'

        // Impedisce a JavaScript di leggere la sessione
        if (ini_get('session.use_only_cookies') === '0') {
            ini_set('session.use_only_cookies', '1');
        }
        
        // Utilizza solo cookie per la sessione (previene session fixation via URL)
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_trans_sid', '0');
        
        // Cookie di sessione sicuro
        ini_set('session.cookie_lifetime', '0'); // Sessione scade alla chiusura del browser
        ini_set('session.gc_maxlifetime', '7200'); // 2 ore max

        // Recupera i parametri correnti dei cookie di sessione
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0, // Scade alla chiusura del browser
            'path' => $cookieParams["path"],
            'domain' => $cookieParams["domain"],
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);

        session_name($session_name);
        session_start();
        
        // Applica sicurezza sessione avanzata
        enforce_session_security();
    }
}

// Avvia sempre la sessione sicura
sec_session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['login_fingerprint']);
}

function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    $db = getDbConnection();
    $stmt = $db->prepare("SELECT u.id, u.username, u.nome_completo, u.ruolo, u.nazionalita, u.creato_il, a.modello AS modello_auto, a.consumo_medio, a.targa FROM utenti u LEFT JOIN auto a ON u.id = a.utente_id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Se l'utente non esiste più nel DB, forza il logout
    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    return $user;
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

// Funzione per creare il login fingerprint (anti session hijacking)
function create_login_fingerprint(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $ua . $ip . 'mpm_salt_2024');
}
