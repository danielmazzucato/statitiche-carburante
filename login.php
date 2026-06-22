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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $db = getDbConnection();
            $stmt = $db->prepare("SELECT id, username, password, nome_completo, ruolo FROM utenti WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Rigenera ID sessione per sicurezza
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nome_completo'] = $user['nome_completo'];
                $_SESSION['ruolo'] = $user['ruolo'];

                // Reindirizzamento in base al ruolo
                if ($user['ruolo'] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: agente.php");
                }
                exit();
            } else {
                $errore = "Nome utente o password non validi.";
            }
        } catch (PDOException $e) {
            $errore = "Errore del database: " . $e->getMessage();
        }
    } else {
        $errore = "Inserisci sia il nome utente che la password.";
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
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="icon" href="favicon.png" type="image/png">
    <!-- Lucide Icons Library -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <div class="app-wrapper">
        <!-- ===== HEADER MPM ===== -->
        <header class="mpm-header animate-in">
            <div class="mpm-decor-yellow-light"></div>

            <div class="mpm-left-group">
                <div class="mpm-logo-container">
                    <img src="logo.jpg" alt="MPM Logo" class="mpm-logo-img">
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

                <form action="login.php" method="POST">
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
