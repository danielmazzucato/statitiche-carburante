<?php
// login.php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Se è già autenticato, lo reindirizza alla pagina corretta
if (is_logged_in()) {
    if ($_SESSION['ruolo'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: agente.php");
    }
    exit();
}

$errore = '';
$rate_limited = false;

// Controlla se la sessione è scaduta
if (isset($_GET['expired']) && $_GET['expired'] === '1') {
    $errore = "La sessione è scaduta per inattività. Effettua nuovamente l'accesso.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!verify_csrf_token()) {
        $errore = "Richiesta non valida. Ricarica la pagina e riprova.";
    } else {
        $username = sanitize_username($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Rate limiting: massimo 5 tentativi ogni 5 minuti per IP
        $client_ip = get_client_ip();
        $rate_key = 'login_' . $client_ip;
        
        if (!check_rate_limit($rate_key, 5, 300)) {
            $remaining = get_remaining_lockout_time($rate_key, 300);
            $minuti = ceil($remaining / 60);
            $errore = "Troppi tentativi di accesso. Riprova tra {$minuti} minuti.";
            $rate_limited = true;
        } elseif (!empty($username) && !empty($password)) {
            try {
                $db = getDbConnection();
                $stmt = $db->prepare("SELECT id, username, password, nome_completo, ruolo FROM utenti WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Login riuscito - pulisci rate limit
                    clear_rate_limit($rate_key);
                    
                    // Rigenera ID sessione per sicurezza (previene session fixation)
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nome_completo'] = $user['nome_completo'];
                    $_SESSION['ruolo'] = $user['ruolo'];
                    $_SESSION['login_fingerprint'] = create_login_fingerprint();
                    $_SESSION['login_time'] = time();

                    // Reindirizzamento in base al ruolo
                    if ($user['ruolo'] === 'admin') {
                        header("Location: admin.php");
                    } else {
                        header("Location: agente.php");
                    }
                    exit();
                } else {
                    // Login fallito - registra tentativo
                    record_attempt($rate_key);
                    // Messaggio generico (non rivela se l'utente esiste)
                    $errore = "Credenziali non valide. Riprova.";
                    // Delay anti brute-force (rallenta i bot)
                    usleep(random_int(500000, 1500000));
                }
            } catch (PDOException $e) {
                // NON esporre dettagli dell'errore DB in produzione
                error_log('Login DB Error: ' . $e->getMessage());
                $errore = "Errore interno del server. Riprova più tardi.";
            }
        } else {
            $errore = "Inserisci sia il nome utente che la password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPM | Login Sicuro - Statistiche Carburante</title>
    <meta name="description" content="Accesso sicuro all'applicazione di statistiche carburante per agenti e amministratori MPM.">
    
    <!-- PWA Settings -->
    <link rel="manifest" href="manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0b0f19">

    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <!-- Lucide Icons Library -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="app-shell">
    <div class="app-wrapper">
        <!-- ===== HEADER MPM ===== -->
        <header class="mpm-header animate-in">
            <div class="mpm-decor-yellow-light"></div>

            <div class="mpm-left-group">
                <div class="mpm-logo-container">
                    <img src="logo.png" alt="MPM Logo" class="mpm-logo-img">
                </div>

                <div class="mpm-title-group">
                    <div class="mpm-subtitle-wrap">
                        <i data-lucide="cpu" class="subtitle-icon" style="width: 18px; height: 18px;"></i>
                        <span class="mpm-subtitle">Internal Software</span>
                    </div>
                    <h1 class="mpm-title">
                        <span>STATISTICHE</span>
                        <span class="title-highlight">CARBURANTE</span>
                    </h1>
                </div>
            </div>

            <div class="header-meta">
                <button id="theme-toggle" class="theme-toggle" title="Cambia Tema">
                    <i class="moon-icon" data-lucide="moon"></i>
                    <i class="sun-icon" data-lucide="sun"></i>
                </button>
            </div>
        </header>

        <!-- ===== CONTENUTO LOGIN ===== -->
        <main class="animate-up animate-delay-1">
            <div class="login-container">
                <h2 class="login-title">Area Riservata</h2>
                
                <?php if (!empty($errore)): ?>
                    <div class="alert danger">
                        <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                        <span><?php echo htmlspecialchars($errore); ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" autocomplete="off">
                    <?php echo get_csrf_input(); ?>
                    <div class="form-group">
                        <label for="username">Nome Utente</label>
                        <input type="text" id="username" name="username" placeholder="Inserisci il tuo username" required autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Inserisci la password" required autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn-primary">
                        <i data-lucide="log-in" style="width: 20px; height: 20px;"></i>
                        Accedi
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script src="app.js?v=<?php echo time(); ?>"></script>
    <script>
        // Inizializza le icone Lucide all'avvio
        lucide.createIcons();
    </script>
</body>

</html>
