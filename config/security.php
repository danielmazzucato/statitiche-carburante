<?php
// config/security.php
// Modulo di sicurezza centralizzato per l'applicazione in produzione

// ============================================================
// 1. SECURITY HEADERS - Protezione browser
// ============================================================
function set_security_headers() {
    // Previene clickjacking (iframe da siti esterni)
    header('X-Frame-Options: DENY');
    
    // Previene MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Protezione XSS del browser
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer policy per privacy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions policy (disabilita funzionalità non necessarie)
    header('Permissions-Policy: camera=self, microphone=(), geolocation=(), payment=()');
    
    // Content Security Policy - previene XSS e injection
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
    
    // Strict Transport Security (HTTPS obbligatorio)
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Rimuove header che espongono info sul server
    header_remove('X-Powered-By');
    header_remove('Server');
}

// ============================================================
// 2. CSRF PROTECTION - Protezione Cross-Site Request Forgery
// ============================================================
function generate_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function get_csrf_input(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function verify_csrf_token(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    // Rigenera il token dopo la verifica (one-time use)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $valid;
}

// ============================================================
// 3. RATE LIMITING - Protezione brute force login
// ============================================================
function check_rate_limit(string $identifier, int $max_attempts = 5, int $window_seconds = 300): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $key = 'rate_limit_' . md5($identifier);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    // Rimuovi tentativi scaduti
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $window_seconds) {
        return ($now - $timestamp) < $window_seconds;
    });
    
    // Controlla se il limite è superato
    if (count($_SESSION[$key]) >= $max_attempts) {
        return false; // Troppi tentativi
    }
    
    return true;
}

function record_attempt(string $identifier): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $key = 'rate_limit_' . md5($identifier);
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    $_SESSION[$key][] = time();
}

function clear_rate_limit(string $identifier): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $key = 'rate_limit_' . md5($identifier);
    unset($_SESSION[$key]);
}

function get_remaining_lockout_time(string $identifier, int $window_seconds = 300): int {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $key = 'rate_limit_' . md5($identifier);
    if (!isset($_SESSION[$key]) || empty($_SESSION[$key])) {
        return 0;
    }
    $oldest = min($_SESSION[$key]);
    $remaining = $window_seconds - (time() - $oldest);
    return max(0, $remaining);
}

// ============================================================
// 4. INPUT SANITIZATION - Pulizia input avanzata
// ============================================================
function sanitize_string(string $input): string {
    $input = trim($input);
    $input = strip_tags($input);
    // Rimuovi caratteri di controllo invisibili
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    return $input;
}

function sanitize_username(string $input): string {
    // Permetti solo lettere, numeri, punti e underscore
    $input = trim($input);
    $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
    return substr($input, 0, 50); // Limite lunghezza
}

function sanitize_filename(string $filename): string {
    // Rimuovi path traversal e caratteri pericolosi
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    // Blocca estensioni pericolose
    $dangerous_ext = ['php', 'phtml', 'pht', 'php3', 'php4', 'php5', 'php7', 'phps', 'cgi', 'pl', 'py', 'sh', 'bat', 'exe', 'cmd', 'com', 'htaccess'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, $dangerous_ext)) {
        $filename .= '.blocked';
    }
    return $filename;
}

// ============================================================
// 5. FILE UPLOAD SECURITY - Validazione upload sicura
// ============================================================
function validate_uploaded_image(array $file, int $max_size_mb = 5): array {
    $result = ['valid' => false, 'error' => '', 'mime' => ''];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Errore durante il caricamento del file.';
        return $result;
    }
    
    // Verifica dimensione
    $max_bytes = $max_size_mb * 1024 * 1024;
    if ($file['size'] > $max_bytes) {
        $result['error'] = "Il file supera la dimensione massima di {$max_size_mb}MB.";
        return $result;
    }
    
    // Verifica MIME type reale (non basato sull'estensione)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    
    if (!array_key_exists($mime, $allowed_mimes)) {
        $result['error'] = 'Formato file non consentito. Usa JPG, PNG o WebP.';
        return $result;
    }
    
    // Verifica che sia effettivamente un'immagine valida
    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $result['error'] = 'Il file non è un\'immagine valida.';
        return $result;
    }
    
    // Controlla che il nome file non contenga path traversal
    if (strpos($file['name'], '..') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
        $result['error'] = 'Nome file non valido.';
        return $result;
    }
    
    $result['valid'] = true;
    $result['mime'] = $mime;
    $result['extension'] = $allowed_mimes[$mime];
    return $result;
}

function validate_uploaded_document(array $file, int $max_size_mb = 10): array {
    $result = ['valid' => false, 'error' => '', 'mime' => ''];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Errore durante il caricamento del file.';
        return $result;
    }
    
    $max_bytes = $max_size_mb * 1024 * 1024;
    if ($file['size'] > $max_bytes) {
        $result['error'] = "Il file supera la dimensione massima di {$max_size_mb}MB.";
        return $result;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    
    if (!array_key_exists($mime, $allowed_mimes)) {
        $result['error'] = 'Formato file non consentito. Usa PDF, JPG, PNG o WebP.';
        return $result;
    }
    
    if (strpos($file['name'], '..') !== false || strpos($file['name'], '/') !== false || strpos($file['name'], '\\') !== false) {
        $result['error'] = 'Nome file non valido.';
        return $result;
    }
    
    $result['valid'] = true;
    $result['mime'] = $mime;
    $result['extension'] = $allowed_mimes[$mime];
    return $result;
}

// ============================================================
// 6. ERROR HANDLING - Nasconde errori dettagliati in produzione
// ============================================================
function setup_production_errors(): void {
    // Non mostrare errori PHP all'utente (li logga su file)
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
}

// ============================================================
// 7. UTILITY FUNCTIONS - Utility IP per ambienti proxy
// ============================================================
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// ============================================================
// 8. SESSION SECURITY - Sessioni ultra-sicure
// ============================================================
function enforce_session_security(): void {
    // Rigenera session ID periodicamente (ogni 30 minuti)
    if (isset($_SESSION['last_regeneration'])) {
        if (time() - $_SESSION['last_regeneration'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    } else {
        $_SESSION['last_regeneration'] = time();
    }
    
    // Verifica che l'IP non sia cambiato (protezione session hijacking)
    $client_ip = get_client_ip();
    if (isset($_SESSION['bound_ip'])) {
        if ($_SESSION['bound_ip'] !== $client_ip) {
            // IP cambiato - possibile hijacking, distruggi sessione
            session_unset();
            session_destroy();
            header('Location: login.php');
            exit();
        }
    } else {
        $_SESSION['bound_ip'] = $client_ip;
    }
    
    // Timeout sessione inattiva (2 ore)
    $timeout = 7200;
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            session_unset();
            session_destroy();
            header('Location: login.php?expired=1');
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

// Applica headers di sicurezza automaticamente
set_security_headers();
setup_production_errors();
