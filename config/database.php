<?php
// config/database.php
// Connessione al database PostgreSQL su Supabase

// Carica le variabili d'ambiente dal file .env
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignora i commenti
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) {
            continue;
        }
        // Separa chiave=valore
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Rimuovi virgolette se presenti
            $value = trim($value, '"\'');
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}

// Carica .env dalla root del progetto
loadEnv(__DIR__ . '/../.env');

// Credenziali Supabase (PostgreSQL)
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: '');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');

// Credenziali connessione diretta al DB PostgreSQL di Supabase
define('DB_HOST', getenv('DB_HOST') ?: 'db.xxxxxxxxxxxxx.supabase.co');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');

function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";options='--client_encoding=UTF8'";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

        } catch (PDOException $e) {
            die("Errore di connessione al database Supabase (PostgreSQL): " . $e->getMessage() . "<br><br><b>Verifica le credenziali nel file .env e che il progetto Supabase sia attivo.</b>");
        }
    }

    return $pdo;
}
