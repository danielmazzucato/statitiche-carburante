<?php
// admin.php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/storage.php';

// Assicura che l'utente sia loggato come admin
require_role('admin');

$db = getDbConnection();
$current_user = get_logged_user();

// Helper per formattare la data con il giorno della settimana in italiano
function formattaDataItaliana(string $date_str) {
    $timestamp = strtotime($date_str);
    $giorni = [
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
        7 => 'Domenica'
    ];
    $num_giorno = date('N', $timestamp);
    $nome_giorno = $giorni[$num_giorno] ?? '';
    return $nome_giorno . ' ' . date('d/m/Y H:i', $timestamp);
}

// Helper per verificare la complessità della password (minimo 12 caratteri, maiuscole, minuscole, numeri e simboli)
function isPasswordStrong(string $password) {
    if (strlen($password) < 12) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    return true;
}

$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Sanitizzazione messaggi (previene XSS via URL)
$success_msg = htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8');
$error_msg = htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8');

// Determinazione del Tab Attivo
$tab = $_GET['tab'] ?? 'inserimenti';
if (!in_array($tab, ['inserimenti', 'agenti', 'auto', 'statistiche', 'impostazioni'])) {
    $tab = 'inserimenti';
}

// Gestione Modifica Utente (carica dati se in modalità edit)
$edit_user = null;
if ($tab === 'agenti' && isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $db->prepare("SELECT id, username, nome_completo, ruolo, nazionalita FROM utenti WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_user = $stmt->fetch();
    }
}

// Gestione Modifica Auto
$edit_auto = null;
if ($tab === 'auto' && isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $db->prepare("SELECT id, utente_id, modello, targa, consumo_medio, documenti FROM auto WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_auto = $stmt->fetch();
    }
}

// =========================================
// GESTIONE AZIONI POST (CRUD & SETTINGS)
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token per tutte le azioni POST
    if (!verify_csrf_token()) {
        header("Location: admin.php?tab=" . urlencode($tab) . "&error=" . urlencode("Richiesta non valida. Ricarica la pagina e riprova."));
        exit();
    }
    
    $action = $_POST['action'] ?? '';

    // 1. Elimina Inserimento
    if ($action === 'delete_inserimento') {
        $inserimento_id = filter_input(INPUT_POST, 'inserimento_id', FILTER_VALIDATE_INT);
        if ($inserimento_id) {
            try {
                // Recupera percorso foto per eliminarla da Supabase Storage
                $stmt = $db->prepare("SELECT foto_path FROM inserimenti_utente WHERE id = ?");
                $stmt->execute([$inserimento_id]);
                $foto_path = $stmt->fetchColumn();
                
                if ($foto_path) {
                    supabaseStorageDelete(extractStorageFilename($foto_path));
                }

                $stmt = $db->prepare("DELETE FROM inserimenti_utente WHERE id = ?");
                $stmt->execute([$inserimento_id]);
                header("Location: admin.php?tab=inserimenti&success=" . urlencode("Inserimento eliminato con successo."));
                exit();
            } catch (PDOException $e) {
                header("Location: admin.php?tab=inserimenti&error=" . urlencode("Errore: " . $e->getMessage()));
                exit();
            }
        }
    }

    // 2. Aggiungi Utente
    if ($action === 'add_user') {
        $username = sanitize_username($_POST['username'] ?? '');
        $nome_completo = sanitize_string($_POST['nome_completo'] ?? '');
        $password = $_POST['password'] ?? '';
        $ruolo = $_POST['ruolo'] ?? 'agente';
        $nazionalita = $_POST['nazionalita'] ?? 'italia';

        // Validazione nazionalità
        $nazionalita_valide = ['italia', 'spagna', 'francia', 'belgio', 'stati_uniti'];
        if (!in_array($nazionalita, $nazionalita_valide)) {
            $nazionalita = 'italia';
        }

        if (empty($username) || empty($nome_completo) || empty($password)) {
            header("Location: admin.php?tab=impostazioni&error=" . urlencode("Compila tutti i campi obbligatori (Username, Nome Completo, Password)."));
            exit();
        } elseif (!isPasswordStrong($password)) {
            header("Location: admin.php?tab=impostazioni&error=" . urlencode("Errore: la password deve essere lunga almeno 12 caratteri e contenere lettere maiuscole, minuscole, numeri e simboli."));
            exit();
        } else {
            try {
                // Verifica username univoco
                $stmt = $db->prepare("SELECT COUNT(*) FROM utenti WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    header("Location: admin.php?tab=impostazioni&error=" . urlencode("Errore: lo username '" . $username . "' è già utilizzato."));
                    exit();
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO utenti (username, password, nome_completo, ruolo, nazionalita) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $username,
                    $hashed_password,
                    $nome_completo,
                    $ruolo,
                    $nazionalita
                ]);

                header("Location: admin.php?tab=impostazioni&success=" . urlencode("Nuovo utente creato con successo."));
                exit();
            } catch (PDOException $e) {
                header("Location: admin.php?tab=impostazioni&error=" . urlencode("Errore database: " . $e->getMessage()));
                exit();
            }
        }
    }

    // 3. Modifica Utente
    if ($action === 'edit_user') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $username = sanitize_username($_POST['username'] ?? '');
        $nome_completo = sanitize_string($_POST['nome_completo'] ?? '');
        $password = $_POST['password'] ?? '';
        $ruolo = $_POST['ruolo'] ?? 'agente';
        $nazionalita = $_POST['nazionalita'] ?? 'italia';

        // Validazione nazionalità
        $nazionalita_valide = ['italia', 'spagna', 'francia', 'belgio', 'stati_uniti'];
        if (!in_array($nazionalita, $nazionalita_valide)) {
            $nazionalita = 'italia';
        }

        if (!$user_id || empty($username) || empty($nome_completo)) {
            header("Location: admin.php?tab=agenti&edit=" . $user_id . "&error=" . urlencode("Compila tutti i campi obbligatori per la modifica."));
            exit();
        } else {
            try {
                // Verifica username univoco escludendo l'utente stesso
                $stmt = $db->prepare("SELECT COUNT(*) FROM utenti WHERE username = ? AND id != ?");
                $stmt->execute([$username, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    header("Location: admin.php?tab=agenti&edit=" . $user_id . "&error=" . urlencode("Errore: lo username '" . $username . "' è già utilizzato da un altro profilo."));
                    exit();
                }

                // Aggiornamento utenti
                if (!empty($password)) {
                    if (!isPasswordStrong($password)) {
                        header("Location: admin.php?tab=agenti&edit=" . $user_id . "&error=" . urlencode("Errore: la nuova password deve essere lunga almeno 12 caratteri e contenere lettere maiuscole, minuscole, numeri e simboli."));
                        exit();
                    }
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE utenti SET username = ?, password = ?, nome_completo = ?, ruolo = ?, nazionalita = ? WHERE id = ?");
                    $stmt->execute([
                        $username,
                        $hashed_password,
                        $nome_completo,
                        $ruolo,
                        $nazionalita,
                        $user_id
                    ]);
                } else {
                    $stmt = $db->prepare("UPDATE utenti SET username = ?, nome_completo = ?, ruolo = ?, nazionalita = ? WHERE id = ?");
                    $stmt->execute([
                        $username,
                        $nome_completo,
                        $ruolo,
                        $nazionalita,
                        $user_id
                    ]);
                }

                header("Location: admin.php?tab=agenti&success=" . urlencode("Dati utente aggiornati correttamente."));
                exit();
            } catch (PDOException $e) {
                header("Location: admin.php?tab=agenti&edit=" . $user_id . "&error=" . urlencode("Errore database: " . $e->getMessage()));
                exit();
            }
        }
    }

    // 4. Elimina Utente
    if ($action === 'delete_user') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ($user_id) {
            // Evita di auto-eliminarsi
            if ($user_id === $current_user['id']) {
                header("Location: admin.php?tab=agenti&error=" . urlencode("Non puoi eliminare il profilo amministratore con cui sei attualmente connesso."));
                exit();
            }

            try {
                // Elimina le immagini associate agli inserimenti dell'utente da Supabase Storage
                $stmt = $db->prepare("SELECT foto_path FROM inserimenti_utente WHERE utente_id = ?");
                $stmt->execute([$user_id]);
                $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($photos as $photo) {
                    if ($photo) {
                        supabaseStorageDelete(extractStorageFilename($photo));
                    }
                }

                $stmt = $db->prepare("DELETE FROM utenti WHERE id = ?");
                $stmt->execute([$user_id]);
                header("Location: admin.php?tab=agenti&success=" . urlencode("Utente ed i suoi inserimenti associati sono stati eliminati."));
                exit();
            } catch (PDOException $e) {
                header("Location: admin.php?tab=agenti&error=" . urlencode("Errore database: " . $e->getMessage()));
                exit();
            }
        }
    }

    // 5. Salva Prezzi Carburante
    if ($action === 'update_settings') {
        $paesi_keys = ['italia', 'spagna', 'francia', 'belgio', 'stati_uniti'];
        $error = false;
        
        try {
            $stmt = $db->prepare("UPDATE configurazioni SET valore = ? WHERE chiave = ?");
            foreach ($paesi_keys as $key) {
                $field_name = 'prezzo_carburante_' . $key;
                $prezzo = filter_input(INPUT_POST, $field_name, FILTER_VALIDATE_FLOAT);
                if ($prezzo !== false && $prezzo >= 0) {
                    $stmt->execute([$prezzo, $field_name]);
                } else {
                    $error = true;
                }
            }
            if ($error) {
                header("Location: admin.php?tab=impostazioni&error=" . urlencode("Alcuni prezzi inseriti non sono validi."));
            } else {
                header("Location: admin.php?tab=impostazioni&success=" . urlencode("Prezzi carburante nazionali salvati correttamente."));
            }
            exit();
        } catch (PDOException $e) {
            header("Location: admin.php?tab=impostazioni&error=" . urlencode("Errore database: " . $e->getMessage()));
            exit();
        }
    }

    // 6. Aggiungi Auto
    if ($action === 'add_auto') {
        $modello = trim($_POST['modello'] ?? '');
        $targa = trim($_POST['targa'] ?? '');
        $consumo_medio = filter_input(INPUT_POST, 'consumo_medio', FILTER_VALIDATE_FLOAT);
        $utente_id = filter_input(INPUT_POST, 'utente_id', FILTER_VALIDATE_INT);
        if (!$utente_id) $utente_id = null;

        if (empty($modello) || empty($targa) || empty($consumo_medio)) {
            header("Location: admin.php?tab=impostazioni&error=" . urlencode("Compila tutti i campi obbligatori per l'auto."));
            exit();
        } else {
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM auto WHERE targa = ?");
                $stmt->execute([$targa]);
                if ($stmt->fetchColumn() > 0) {
                    header("Location: admin.php?tab=impostazioni&error=" . urlencode("Errore: la targa '" . $targa . "' è già registrata."));
                    exit();
                }

                if ($utente_id) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM auto WHERE utente_id = ?");
                    $stmt->execute([$utente_id]);
                    if ($stmt->fetchColumn() > 0) {
                        header("Location: admin.php?tab=impostazioni&error=" . urlencode("Errore: l'agente selezionato ha già un'auto associata."));
                        exit();
                    }
                }

                $doc_path = null;
                if (isset($_FILES['documenti']) && $_FILES['documenti']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['documenti'];
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $doc_mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $filename = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $public_url = supabaseStorageUpload($file['tmp_name'], $filename, $doc_mime);
                    if ($public_url) {
                        $doc_path = $public_url;
                    } else {
                        header("Location: admin.php?tab=impostazioni&error=" . urlencode("Impossibile caricare il documento su Supabase Storage."));
                        exit();
                    }
                }

                $stmt = $db->prepare("INSERT INTO auto (utente_id, modello, targa, consumo_medio, documenti) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$utente_id, $modello, $targa, $consumo_medio, $doc_path]);

                header("Location: admin.php?tab=impostazioni&success=" . urlencode("Nuova auto registrata con successo."));
                exit();
            } catch (PDOException $e) {
                header("Location: admin.php?tab=impostazioni&error=" . urlencode("Errore database: " . $e->getMessage()));
                exit();
            }
        }
    }

    // 7. Modifica Auto
    if ($action === 'edit_auto') {
        $auto_id = filter_input(INPUT_POST, 'auto_id', FILTER_VALIDATE_INT);
        $modello = trim($_POST['modello'] ?? '');
        $targa = trim($_POST['targa'] ?? '');
        $consumo_medio = filter_input(INPUT_POST, 'consumo_medio', FILTER_VALIDATE_FLOAT);
        $utente_id = filter_input(INPUT_POST, 'utente_id', FILTER_VALIDATE_INT);
        if (!$utente_id) $utente_id = null;

        if (!$auto_id || empty($modello) || empty($targa) || empty($consumo_medio)) {
            header("Location: admin.php?tab=auto&edit=" . $auto_id . "&error=" . urlencode("Compila tutti i campi obbligatori per l'auto."));
            exit();
        } else {
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM auto WHERE targa = ? AND id != ?");
                $stmt->execute([$targa, $auto_id]);
                if ($stmt->fetchColumn() > 0) {
                    header("Location: admin.php?tab=auto&edit=" . $auto_id . "&error=" . urlencode("Errore: la targa '" . $targa . "' è già registrata."));
                    exit();
                }

                if ($utente_id) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM auto WHERE utente_id = ? AND id != ?");
                    $stmt->execute([$utente_id, $auto_id]);
                    if ($stmt->fetchColumn() > 0) {
                        header("Location: admin.php?tab=auto&edit=" . $auto_id . "&error=" . urlencode("Errore: l'agente selezionato ha già un'auto associata."));
                        exit();
                    }
                }

                $doc_path = null;
                $update_doc = false;
                if (isset($_FILES['documenti']) && $_FILES['documenti']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['documenti'];
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $doc_mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $filename = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $public_url = supabaseStorageUpload($file['tmp_name'], $filename, $doc_mime);
                    if ($public_url) {
                        $doc_path = $public_url;
                        $update_doc = true;
                    } else {
                        header("Location: admin.php?tab=auto&edit=" . $auto_id . "&error=" . urlencode("Impossibile caricare il documento su Supabase Storage."));
                        exit();
                    }
                }

                if ($update_doc) {
                    $stmt = $db->prepare("UPDATE auto SET utente_id = ?, modello = ?, targa = ?, consumo_medio = ?, documenti = ? WHERE id = ?");
                    $stmt->execute([$utente_id, $modello, $targa, $consumo_medio, $doc_path, $auto_id]);
                } else {
                    $stmt = $db->prepare("UPDATE auto SET utente_id = ?, modello = ?, targa = ?, consumo_medio = ? WHERE id = ?");
                    $stmt->execute([$utente_id, $modello, $targa, $consumo_medio, $auto_id]);
                }

                header("Location: admin.php?tab=auto&success=" . urlencode("Dati auto aggiornati con successo."));
                exit();
            } catch (PDOException $e) {
                header("Location: admin.php?tab=auto&edit=" . $auto_id . "&error=" . urlencode("Errore database: " . $e->getMessage()));
                exit();
            }
        }
    }

    // 8. Elimina Auto
    if ($action === 'delete_auto') {
        $auto_id = filter_input(INPUT_POST, 'auto_id', FILTER_VALIDATE_INT);
        if ($auto_id) {
            try {
                $stmt = $db->prepare("SELECT documenti FROM auto WHERE id = ?");
                $stmt->execute([$auto_id]);
                $doc_path = $stmt->fetchColumn();
                
                if ($doc_path) {
                    supabaseStorageDelete(extractStorageFilename($doc_path));
                }

                $stmt = $db->prepare("DELETE FROM auto WHERE id = ?");
                $stmt->execute([$auto_id]);
                header("Location: admin.php?tab=auto&success=" . urlencode("Auto eliminata con successo."));
                exit();
            } catch (PDOException $e) {
                header("Location: admin.php?tab=auto&error=" . urlencode("Errore database: " . $e->getMessage()));
                exit();
            }
        }
    }
}

// Carica Prezzi Carburante per i 5 paesi
$prezzi_paesi = [
    'italia' => 1.80,
    'spagna' => 1.65,
    'francia' => 1.85,
    'belgio' => 1.75,
    'stati_uniti' => 0.95
];
try {
    $stmt = $db->query("SELECT chiave, valore FROM configurazioni WHERE chiave LIKE 'prezzo_carburante_%'");
    $configs = $stmt->fetchAll();
    foreach ($configs as $c) {
        $paese = str_replace('prezzo_carburante_', '', $c['chiave']);
        if (array_key_exists($paese, $prezzi_paesi)) {
            $prezzi_paesi[$paese] = floatval($c['valore']);
        }
    }
} catch (PDOException $e) {
    // Gestione errore silenziosa
}

// Carica elenco agenti per i dropdown
$agenti = [];
try {
    $stmt = $db->query("SELECT u.id, u.username, u.nome_completo, u.nazionalita, a.modello AS modello_auto, a.targa, a.consumo_medio FROM utenti u LEFT JOIN auto a ON u.id = a.utente_id WHERE u.ruolo = 'agente' ORDER BY u.nome_completo ASC");
    $agenti = $stmt->fetchAll();
} catch (PDOException $e) {
    // Gestione errore silenziosa
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPM | Area Admin - Statistiche Carburante</title>
    
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
        <!-- ===== HEADER MPM (Identico al BI) ===== -->
        <header class="mpm-header animate-in">
            <div class="mpm-decor-yellow-light"></div>

            <div class="mpm-left-group">
                <div class="mpm-logo-container">
                    <img src="logo.jpg" alt="MPM Logo" class="mpm-logo-img">
                </div>

                <div class="mpm-title-group">
                    <div class="mpm-subtitle-wrap">
                        <i data-lucide="shield" class="subtitle-icon" style="width: 18px; height: 18px;"></i>
                        <span class="mpm-subtitle">Area Amministratore</span>
                    </div>
                    <h1 class="mpm-title">
                        <span>PANNELLO DI</span>
                        <span class="title-highlight">CONTROLLO</span>
                    </h1>
                </div>
            </div>

            <div class="header-meta">
                <!-- Bottone Impostazioni (Vicino alla luna) -->
                <a href="admin.php?tab=impostazioni" class="theme-toggle <?php echo $tab === 'impostazioni' ? 'active' : ''; ?>" title="Impostazioni Anagrafica">
                    <i data-lucide="settings"></i>
                </a>
                <!-- Tema Toggle (Luna/Sole) -->
                <button id="theme-toggle" class="theme-toggle" title="Cambia Tema">
                    <i class="moon-icon" data-lucide="moon"></i>
                    <i class="sun-icon" data-lucide="sun"></i>
                </button>
                <!-- Logout -->
                <a href="logout.php" class="btn-logout" title="Esci dal sistema">
                    <i data-lucide="log-out"></i>
                </a>
            </div>
        </header>

        <!-- ===== SEZIONE MESSAGGI ===== -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert info animate-in">
                <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                <span><?php echo htmlspecialchars($success_msg); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert danger animate-in">
                <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                <span><?php echo htmlspecialchars($error_msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- ===== TABS DI NAVIGAZIONE ===== -->
        <nav class="admin-tabs animate-in">
            <a href="admin.php?tab=inserimenti" class="tab-btn <?php echo $tab === 'inserimenti' ? 'active' : ''; ?>">
                <i data-lucide="database" style="width: 16px; height: 16px;"></i> Registro Inserimenti
            </a>
            <a href="admin.php?tab=agenti" class="tab-btn <?php echo $tab === 'agenti' ? 'active' : ''; ?>">
                <i data-lucide="users" style="width: 16px; height: 16px;"></i> Agenti
            </a>
            <a href="admin.php?tab=auto" class="tab-btn <?php echo $tab === 'auto' ? 'active' : ''; ?>">
                <i data-lucide="car" style="width: 16px; height: 16px;"></i> Auto
            </a>
            <a href="admin.php?tab=statistiche" class="tab-btn <?php echo $tab === 'statistiche' ? 'active' : ''; ?>">
                <i data-lucide="trending-up" style="width: 16px; height: 16px;"></i> Calcolo Statistiche
            </a>
        </nav>

        <!-- =========================================
             TAB 1: INSERIMENTI AGENTI (LISTA E FILTRI)
             ========================================= -->
        <?php if ($tab === 'inserimenti'): 
            // Recupero parametri di filtraggio
            $filtro_utente = filter_input(INPUT_GET, 'filtro_utente', FILTER_VALIDATE_INT);
            $filtro_anno = filter_input(INPUT_GET, 'filtro_anno', FILTER_VALIDATE_INT);
            $filtro_mese = filter_input(INPUT_GET, 'filtro_mese', FILTER_VALIDATE_INT);

            // Generazione query con filtri dinamici
            $where_clauses = [];
            $params = [];

            if ($filtro_utente) {
                $where_clauses[] = "l.utente_id = ?";
                $params[] = $filtro_utente;
            }
            if ($filtro_anno) {
                $where_clauses[] = "YEAR(l.data_inserimento) = ?";
                $params[] = $filtro_anno;
            }
            if ($filtro_mese) {
                $where_clauses[] = "MONTH(l.data_inserimento) = ?";
                $params[] = $filtro_mese;
            }

            $where_sql = '';
            if (count($where_clauses) > 0) {
                $where_sql = "WHERE " . implode(" AND ", $where_clauses);
            }

            // Carica anni disponibili per il filtro
            $anni = [];
            try {
                $anni_stmt = $db->query("SELECT DISTINCT YEAR(data_inserimento) AS y FROM inserimenti_utente ORDER BY y DESC");
                $anni = $anni_stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {}

            // Carica inserimenti filtrati
            $inserimenti = [];
            try {
                $inserimenti_query = "
                    SELECT l.id, l.utente_id, l.username, l.modello_auto, l.consumo_medio, l.data_inserimento, l.km, l.foto_path, u.nome_completo 
                    FROM inserimenti_utente l
                    LEFT JOIN utenti u ON l.utente_id = u.id
                    $where_sql
                    ORDER BY l.data_inserimento DESC
                ";
                $inserimenti_stmt = $db->prepare($inserimenti_query);
                $inserimenti_stmt->execute($params);
                $inserimenti = $inserimenti_stmt->fetchAll();
            } catch (PDOException $e) {
                echo "<p class='alert danger'>Errore caricamento inserimenti: " . $e->getMessage() . "</p>";
            }
        ?>
            <section class="animate-up">
                <!-- Filtri di ricerca -->
                <div class="filters-bar">
                    <form action="admin.php" method="GET" class="filters-form">
                        <input type="hidden" name="tab" value="inserimenti">
                        
                        <div class="filter-group">
                            <label for="filtro_utente">Agente</label>
                            <select id="filtro_utente" name="filtro_utente">
                                <option value="">Tutti gli agenti</option>
                                <?php foreach ($agenti as $ag): ?>
                                    <option value="<?php echo $ag['id']; ?>" <?php echo $filtro_utente == $ag['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ag['nome_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filtro_anno">Anno</label>
                            <select id="filtro_anno" name="filtro_anno">
                                <option value="">Tutti gli anni</option>
                                <?php foreach ($anni as $a): ?>
                                    <option value="<?php echo $a; ?>" <?php echo $filtro_anno == $a ? 'selected' : ''; ?>>
                                        <?php echo $a; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filtro_mese">Mese</label>
                            <select id="filtro_mese" name="filtro_mese">
                                <option value="">Tutti i mesi</option>
                                <?php 
                                $mesi = [
                                    1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
                                    5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
                                    9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
                                ];
                                foreach ($mesi as $m_num => $m_nome): ?>
                                    <option value="<?php echo $m_num; ?>" <?php echo $filtro_mese == $m_num ? 'selected' : ''; ?>>
                                        <?php echo $m_nome; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary" style="width: auto; height: 42px; padding: 0 1.5rem;">
                            <i data-lucide="filter" style="width: 16px; height: 16px;"></i> Filtra
                        </button>
                        
                        <?php if ($filtro_utente || $filtro_anno || $filtro_mese): ?>
                            <a href="admin.php?tab=inserimenti" class="btn-reset-filters">
                                <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i> Rimuovi Filtri
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabella degli Inserimenti -->
                <div class="data-table-container">
                    <div class="card-title" style="justify-content: space-between; margin-bottom: 1.5rem;">
                        <span style="display:flex; align-items:center; gap:0.5rem;"><i data-lucide="clipboard" style="width: 22px; height: 22px;"></i> Registro Inserimenti Carburante</span>
                        <span style="font-size: 0.85rem; font-weight: bold; background: var(--border-color); padding: 0.3rem 0.8rem; border-radius: 20px; color: var(--text-muted);">
                            <?php echo count($inserimenti); ?> risultati trovati
                        </span>
                    </div>

                    <?php if (count($inserimenti) === 0): ?>
                        <p style="text-align: center; padding: 3rem; color: var(--text-muted); font-weight: bold;">
                            Nessun inserimento corrisponde ai criteri di ricerca selezionati.
                        </p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Agente</th>
                                    <th class="col-hide-mobile">Username</th>
                                    <th class="col-hide-mobile">Modello Auto (Storico)</th>
                                    <th class="col-hide-mobile">Consumo Medio (Storico)</th>
                                    <th>Data & Ora</th>
                                    <th>Chilometri</th>
                                    <th>Foto</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inserimenti as $l): ?>
                                    <tr>
                                        <td style="font-weight: 700; color: var(--text-main);">
                                            <?php echo htmlspecialchars($l['nome_completo'] ?? 'Utente Eliminato'); ?>
                                        </td>
                                        <td class="col-hide-mobile"><code><?php echo htmlspecialchars($l['username']); ?></code></td>
                                        <td class="col-hide-mobile"><?php echo htmlspecialchars($l['modello_auto']); ?></td>
                                        <td class="col-hide-mobile"><?php echo number_format($l['consumo_medio'], 2, ',', '.'); ?> km/l</td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($l['data_inserimento'])); ?></td>
                                        <td style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;">
                                            <?php echo number_format($l['km'], 0, ',', '.'); ?> km
                                        </td>
                                        <td>
                                            <?php if ($l['foto_path']): ?>
                                                <img src="<?php echo htmlspecialchars($l['foto_path']); ?>" 
                                                     class="img-thumbnail" 
                                                     data-full-src="<?php echo htmlspecialchars($l['foto_path']); ?>" 
                                                     data-caption="<?php echo htmlspecialchars($l['nome_completo'] ?? $l['username']); ?> - <?php echo date('d/m/Y H:i', strtotime($l['data_inserimento'])); ?> (<?php echo number_format($l['km'], 0, ',', '.'); ?> km)"
                                                     alt="Contachilometri">
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.8rem; font-style: italic;">Nessuna</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form action="admin.php?tab=inserimenti" method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare definitivamente questo inserimento?');" style="display:inline;">
                                                <?php echo get_csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete_inserimento">
                                                <input type="hidden" name="inserimento_id" value="<?php echo $l['id']; ?>">
                                                <button type="submit" class="btn-danger">
                                                    <i data-lucide="trash-2" style="width: 13px; height: 13px;"></i> Elimina
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

        <!-- =========================================
             TAB 2: CALCOLO STATISTICHE
             ========================================= -->
        <?php elseif ($tab === 'statistiche'): 
            $utente_id = filter_input(INPUT_GET, 'utente_id', FILTER_VALIDATE_INT);
            $inserimento_inizio_id = filter_input(INPUT_GET, 'lancio_inizio_id', FILTER_VALIDATE_INT);
            $inserimento_fine_id = filter_input(INPUT_GET, 'lancio_fine_id', FILTER_VALIDATE_INT);
            $filtro_stat_anno = filter_input(INPUT_GET, 'filtro_stat_anno', FILTER_VALIDATE_INT);
            $filtro_stat_mese = filter_input(INPUT_GET, 'filtro_stat_mese', FILTER_VALIDATE_INT);

            $inserimenti_agente = [];
            $selected_agente = null;

            $anni_stat = [];
            try {
                $stmt = $db->query("SELECT DISTINCT YEAR(data_inserimento) AS y FROM inserimenti_utente ORDER BY y DESC");
                $anni_stat = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {}

            if ($utente_id) {
                // Carica dettagli dell'agente selezionato
                $stmt = $db->prepare("SELECT u.id, u.username, u.nome_completo, u.nazionalita, a.modello AS modello_auto, a.targa, a.consumo_medio FROM utenti u LEFT JOIN auto a ON u.id = a.utente_id WHERE u.id = ? AND u.ruolo = 'agente'");
                $stmt->execute([$utente_id]);
                $selected_agente = $stmt->fetch();

                if ($selected_agente) {
                    $query_inserimenti = "SELECT id, km, data_inserimento FROM inserimenti_utente WHERE utente_id = ?";
                    $params_inserimenti = [$utente_id];

                    if ($filtro_stat_anno) {
                        $query_inserimenti .= " AND YEAR(data_inserimento) = ?";
                        $params_inserimenti[] = $filtro_stat_anno;
                    }
                    if ($filtro_stat_mese) {
                        $query_inserimenti .= " AND MONTH(data_inserimento) = ?";
                        $params_inserimenti[] = $filtro_stat_mese;
                    }
                    $query_inserimenti .= " ORDER BY data_inserimento DESC";

                    $stmt = $db->prepare($query_inserimenti);
                    $stmt->execute($params_inserimenti);
                    $inserimenti_agente = $stmt->fetchAll();
                }
            }
        ?>
            <section class="animate-up">
                <!-- Selezione Agente -->
                <div class="premium-card">
                    <div class="card-title">
                        <i data-lucide="user-check" style="width: 24px; height: 24px;"></i>
                        Analisi Viaggi Agenti
                    </div>
                    
                    <form action="admin.php" method="GET">
                        <input type="hidden" name="tab" value="statistiche">
                        <div class="form-group">
                            <label for="utente_id">Seleziona l'Agente da esaminare</label>
                            <select id="utente_id" name="utente_id" onchange="this.form.submit()" required>
                                <option value="">-- Scegli un agente --</option>
                                <?php foreach ($agenti as $ag): ?>
                                    <option value="<?php echo $ag['id']; ?>" <?php echo $utente_id == $ag['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ag['nome_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($utente_id): ?>
                            <div class="stats-filters" style="display:flex; gap:1rem; margin-top:1rem;">
                                <div class="form-group" style="flex:1;">
                                    <label for="filtro_stat_anno">Filtra per Anno</label>
                                    <select id="filtro_stat_anno" name="filtro_stat_anno" onchange="this.form.submit()">
                                        <option value="">Tutti gli anni</option>
                                        <?php foreach ($anni_stat as $a): ?>
                                            <option value="<?php echo $a; ?>" <?php echo $filtro_stat_anno == $a ? 'selected' : ''; ?>><?php echo $a; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label for="filtro_stat_mese">Filtra per Mese</label>
                                    <select id="filtro_stat_mese" name="filtro_stat_mese" onchange="this.form.submit()">
                                        <option value="">Tutti i mesi</option>
                                        <?php 
                                        $mesi_nomi = [
                                            1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
                                            5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
                                            9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
                                        ];
                                        foreach ($mesi_nomi as $m_num => $m_nome): ?>
                                            <option value="<?php echo $m_num; ?>" <?php echo $filtro_stat_mese == $m_num ? 'selected' : ''; ?>><?php echo $m_nome; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($selected_agente): ?>
                    <?php if (count($inserimenti_agente) < 2): ?>
                        <div class="alert warning">
                            <i data-lucide="alert-triangle" style="width: 20px; height: 20px;"></i>
                            <span>L'agente selezionato (<?php echo htmlspecialchars($selected_agente['nome_completo']); ?>) ha effettuato meno di 2 inserimenti nel database. È necessario che l'agente effettui almeno due inserimenti (ad esempio, venerdì e lunedì) per poter calcolare una differenza di chilometri e generare le statistiche.</span>
                        </div>
                    <?php else: ?>
                        <!-- Selettore dei due inserimenti -->
                        <form action="admin.php" method="GET" class="stats-selector-grid">
                            <input type="hidden" name="tab" value="statistiche">
                            <input type="hidden" name="utente_id" value="<?php echo $utente_id; ?>">
                            <?php if ($filtro_stat_anno): ?>
                                <input type="hidden" name="filtro_stat_anno" value="<?php echo $filtro_stat_anno; ?>">
                            <?php endif; ?>
                            <?php if ($filtro_stat_mese): ?>
                                <input type="hidden" name="filtro_stat_mese" value="<?php echo $filtro_stat_mese; ?>">
                            <?php endif; ?>

                            <!-- Colonna Inserimento Inizio -->
                            <div class="selection-box">
                                <div class="selection-title">
                                    <i data-lucide="play-circle" style="color:var(--success); width: 22px; height: 22px;"></i>
                                    1. Punto di Inizio
                                </div>
                                <div class="form-group" style="padding: 1rem;">
                                    <select name="lancio_inizio_id" required>
                                        <option value="">-- Seleziona Punto di Inizio --</option>
                                        <?php foreach ($inserimenti_agente as $la): ?>
                                            <option value="<?php echo $la['id']; ?>" <?php echo $inserimento_inizio_id == $la['id'] ? 'selected' : ''; ?>>
                                                <?php echo formattaDataItaliana($la['data_inserimento']); ?> - (<?php echo number_format($la['km'], 0, ',', '.'); ?> km)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Colonna Inserimento Fine -->
                            <div class="selection-box">
                                <div class="selection-title">
                                    <i data-lucide="stop-circle" style="color:var(--danger); width: 22px; height: 22px;"></i>
                                    2. Punto di Fine
                                </div>
                                <div class="form-group" style="padding: 1rem;">
                                    <select name="lancio_fine_id" required>
                                        <option value="">-- Seleziona Punto di Fine --</option>
                                        <?php foreach ($inserimenti_agente as $la): ?>
                                            <option value="<?php echo $la['id']; ?>" <?php echo $inserimento_fine_id == $la['id'] ? 'selected' : ''; ?>>
                                                <?php echo formattaDataItaliana($la['data_inserimento']); ?> - (<?php echo number_format($la['km'], 0, ',', '.'); ?> km)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="grid-column: 1 / -1; text-align: center;">
                                <button type="submit" class="btn-primary" style="width: auto; padding: 1rem 3rem;">
                                    <i data-lucide="calculator" style="width: 20px; height: 20px;"></i> Calcola Statistiche Periodo
                                </button>
                            </div>
                        </form>

                        <!-- Dashboard dei risultati se entrambi gli inserimenti sono selezionati -->
                        <?php 
                        if ($inserimento_inizio_id && $inserimento_fine_id):
                            // Carica i dettagli degli inserimenti selezionati
                            $stmt = $db->prepare("SELECT km, data_inserimento, modello_auto, consumo_medio FROM inserimenti_utente WHERE id = ? AND utente_id = ?");
                            
                            $stmt->execute([$inserimento_inizio_id, $utente_id]);
                            $l_inizio = $stmt->fetch();

                            $stmt->execute([$inserimento_fine_id, $utente_id]);
                            $l_fine = $stmt->fetch();

                            if ($l_inizio && $l_fine):
                                // Validazione date e chilometri
                                $data_ini = strtotime($l_inizio['data_inserimento']);
                                $data_fin = strtotime($l_fine['data_inserimento']);

                                // Se l'utente ha selezionato i record invertiti cronologicamente, li correggiamo al volo
                                if ($data_ini > $data_fin) {
                                    $temp = $l_inizio;
                                    $l_inizio = $l_fine;
                                    $l_fine = $temp;

                                    $data_ini = strtotime($l_inizio['data_inserimento']);
                                    $data_fin = strtotime($l_fine['data_inserimento']);
                                }

                                $km_inizio = $l_inizio['km'];
                                $km_fine = $l_fine['km'];
                                
                                if ($km_fine < $km_inizio): ?>
                                    <div class="alert danger">
                                        <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                                        <span>Errore nel calcolo: i chilometri del punto di fine (<?php echo number_format($km_fine, 0, ',', '.'); ?> km) sono minori rispetto a quelli del punto di inizio (<?php echo number_format($km_inizio, 0, ',', '.'); ?> km). Controlla le date e le letture.</span>
                                    </div>
                                <?php else: 
                                    // Esegui calcoli
                                    $km_percorsi = $km_fine - $km_inizio;
                                    
                                    // Usiamo il consumo medio dell'anagrafica attuale dell'agente
                                    $consumo_medio = floatval($selected_agente['consumo_medio'] ?? $l_inizio['consumo_medio']);
                                    
                                    // Evita divisione per zero
                                    $litri_consumati = $consumo_medio > 0 ? ($km_percorsi / $consumo_medio) : 0;
                                    // Prezzo iniziale basato sulla nazionalità dell'agente
                                    $agente_nazionalita = $selected_agente['nazionalita'] ?? 'italia';
                                    $prezzo_iniziale = $prezzi_paesi[$agente_nazionalita] ?? 1.80;
                                    $costo_stimato = $litri_consumati * $prezzo_iniziale;
                                ?>
                                    <div class="premium-card animate-up" style="border-top: 5px solid var(--accent-dark);">
                                        <div class="card-title" style="justify-content: space-between;">
                                            <span style="display:flex; align-items:center; gap:0.5rem;"><i data-lucide="activity" style="width:24px; height:24px;"></i> Dashboard Statistica Periodo</span>
                                            <span style="font-size: 0.85rem; font-weight: bold; background: var(--cat-venduto-bg); color: var(--accent-dark); padding: 0.4rem 1rem; border-radius: 20px;">
                                                Agente: <?php echo htmlspecialchars($selected_agente['nome_completo']); ?>
                                            </span>
                                        </div>

                                        <p style="margin-bottom: 2rem; font-size: 0.95rem; color: var(--text-muted); font-weight: 500;">
                                            Periodo analizzato: 
                                            <b><?php echo formattaDataItaliana($l_inizio['data_inserimento']); ?></b> (km iniziali: <?php echo number_format($km_inizio, 0, ',', '.'); ?>) 
                                            a 
                                            <b><?php echo formattaDataItaliana($l_fine['data_inserimento']); ?></b> (km finali: <?php echo number_format($km_fine, 0, ',', '.'); ?>)
                                        </p>

                                        <!-- Griglia KPI Premium -->
                                        <div class="kpi-grid">
                                            <!-- Km percorsi -->
                                            <div class="kpi-card">
                                                <div class="kpi-label"><i data-lucide="navigation" style="width:14px; height:14px;"></i> Distanza Percorsa</div>
                                                <div class="kpi-value"><?php echo number_format($km_percorsi, 0, ',', '.'); ?> km</div>
                                                <div class="kpi-sub">Differenza di lettura contachilometri</div>
                                            </div>

                                            <!-- Consumo medio -->
                                            <div class="kpi-card">
                                                <div class="kpi-label"><i data-lucide="gauge" style="width:14px; height:14px;"></i> Consumo Medio Auto</div>
                                                <div class="kpi-value"><?php echo number_format($consumo_medio, 2, ',', '.'); ?> <span style="font-size: 1.2rem;">km/l</span></div>
                                                <div class="kpi-sub">Modello: <?php echo htmlspecialchars(($selected_agente['modello_auto'] ?? $l_inizio['modello_auto']) . (isset($selected_agente['targa']) && $selected_agente['targa'] ? ' (' . $selected_agente['targa'] . ')' : '')); ?></div>
                                            </div>

                                            <!-- Litri consumati -->
                                            <div class="kpi-card">
                                                <div class="kpi-label"><i data-lucide="droplet" style="width:14px; height:14px;"></i> Carburante Stimato</div>
                                                <div class="kpi-value" id="dashboard-litri-value" data-litri="<?php echo $litri_consumati; ?>">
                                                    <?php echo number_format($litri_consumati, 2, ',', '.'); ?> <span style="font-size: 1.2rem;">Litri</span>
                                                </div>
                                                <div class="kpi-sub">Formula: Km Percorsi / Consumo Medio</div>
                                            </div>

                                            <!-- Costo Stimato (ACCENT con Selettore Paese) -->
                                            <div class="kpi-card accent">
                                                <div class="kpi-label" style="font-weight: 950; letter-spacing: 0.05em;"><i data-lucide="credit-card" style="width:14px; height:14px;"></i> Costo Stimato</div>
                                                <div class="kpi-value" id="dashboard-costo-stimato" style="color: #0f172a; margin-bottom: 0.5rem;">
                                                    € <?php echo number_format($costo_stimato, 2, ',', '.'); ?>
                                                </div>
                                                
                                                <div class="kpi-sub">
                                                    <div class="price-input-wrapper" style="flex-direction: column; align-items: stretch; gap: 0.4rem;">
                                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                                            <label for="dashboard-paese-prezzo" style="font-size: 0.7rem; font-weight: 850; text-transform: uppercase;">Paese:</label>
                                                            <select id="dashboard-paese-prezzo" style="font-size: 0.75rem; padding: 0.25rem; border-radius: 6px; border: 1px solid rgba(15,23,42,0.15); background: rgba(255,255,255,0.85); font-weight: bold; width: 110px;">
                                                                <option value="italia" <?php echo $agente_nazionalita === 'italia' ? 'selected' : ''; ?>>Italia</option>
                                                                <option value="spagna" <?php echo $agente_nazionalita === 'spagna' ? 'selected' : ''; ?>>Spagna</option>
                                                                <option value="francia" <?php echo $agente_nazionalita === 'francia' ? 'selected' : ''; ?>>Francia</option>
                                                                <option value="belgio" <?php echo $agente_nazionalita === 'belgio' ? 'selected' : ''; ?>>Belgio</option>
                                                                <option value="stati_uniti" <?php echo $agente_nazionalita === 'stati_uniti' ? 'selected' : ''; ?>>Stati Uniti</option>
                                                            </select>
                                                        </div>
                                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                                            <label for="dashboard-prezzo-carburante" style="font-size: 0.7rem; font-weight: 850; text-transform: uppercase;">Prezzo €/l:</label>
                                                            <input type="number" id="dashboard-prezzo-carburante" value="<?php echo number_format($prezzo_iniziale, 2, '.', ''); ?>" step="0.01" min="0" style="width: 110px;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php endif; ?>
                <?php endif; ?>
            </section>

        <!-- =========================================
             TAB: AGENTI
             ========================================= -->
        <?php elseif ($tab === 'agenti'): 
            $tutti_utenti = [];
            try {
                $stmt = $db->query("SELECT id, username, nome_completo, ruolo, nazionalita, creato_il FROM utenti ORDER BY ruolo ASC, nome_completo ASC");
                $tutti_utenti = $stmt->fetchAll();
            } catch (PDOException $e) {}
        ?>
            <section class="animate-up">
                <?php if ($edit_user): ?>
                    <div class="premium-card" style="margin-bottom: 2rem; border-top: 4px solid var(--warning);">
                        <div class="card-title">
                            <i data-lucide="user-cog" style="width: 24px; height: 24px;"></i>
                            Modifica Anagrafica Agente: <?php echo htmlspecialchars($edit_user['nome_completo']); ?>
                        </div>
                        <form action="admin.php?tab=agenti" method="POST">
                            <?php echo get_csrf_input(); ?>
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">

                            <div class="form-group">
                                <label for="username">Nome Utente (Username) *</label>
                                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required autocomplete="username">
                            </div>

                            <div class="form-group">
                                <label for="nome_completo">Nome Completo (Cognome Nome) *</label>
                                <input type="text" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($edit_user['nome_completo']); ?>" required autocomplete="name">
                            </div>

                            <div class="form-group">
                                <label for="password">Password (Lascia vuota per non modificarla)</label>
                                <input type="password" id="password" name="password" minlength="12" autocomplete="new-password">
                                <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; display: block; margin-top: 0.25rem;">Requisiti: almeno 12 caratteri con lettere maiuscole, minuscole, numeri e simboli.</span>
                            </div>

                            <div class="form-group">
                                <label for="ruolo">Ruolo di Sistema</label>
                                <select id="ruolo" name="ruolo">
                                    <option value="agente" <?php echo $edit_user['ruolo'] === 'agente' ? 'selected' : ''; ?>>Agente</option>
                                    <option value="admin" <?php echo $edit_user['ruolo'] === 'admin' ? 'selected' : ''; ?>>Amministratore</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="nazionalita">Nazionalità</label>
                                <?php $user_naz = $edit_user['nazionalita'] ?? 'italia'; ?>
                                <select id="nazionalita" name="nazionalita">
                                    <option value="italia" <?php echo $user_naz === 'italia' ? 'selected' : ''; ?>>Italia</option>
                                    <option value="spagna" <?php echo $user_naz === 'spagna' ? 'selected' : ''; ?>>Spagna</option>
                                    <option value="francia" <?php echo $user_naz === 'francia' ? 'selected' : ''; ?>>Francia</option>
                                    <option value="belgio" <?php echo $user_naz === 'belgio' ? 'selected' : ''; ?>>Belgio</option>
                                    <option value="stati_uniti" <?php echo $user_naz === 'stati_uniti' ? 'selected' : ''; ?>>Stati Uniti</option>
                                </select>
                            </div>

                            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                                <button type="submit" class="btn-primary" style="flex:1;">Salva Modifiche</button>
                                <a href="admin.php?tab=agenti" class="btn-secondary" style="flex:1;">Annulla</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="data-table-container">
                    <div class="settings-header">
                        <h3 class="settings-title" style="display:flex; align-items:center; gap:0.5rem;"><i data-lucide="users" style="width: 22px; height: 22px;"></i> Lista Agenti</h3>
                        <span style="font-size: 0.85rem; font-weight: bold; background: var(--border-color); padding: 0.3rem 0.8rem; border-radius: 20px; color: var(--text-muted);">
                            Totale: <?php echo count($tutti_utenti); ?>
                        </span>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nome Completo</th>
                                <th class="col-hide-mobile">Username</th>
                                <th class="col-hide-mobile">Ruolo</th>
                                <th class="col-hide-mobile">Nazionalità</th>
                                <th class="col-hide-mobile">Data Creazione</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tutti_utenti as $u): ?>
                                <tr style="<?php echo ($edit_user && $edit_user['id'] === $u['id']) ? 'background: rgba(255, 215, 0, 0.08);' : ''; ?>">
                                    <td style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($u['nome_completo']); ?></td>
                                    <td class="col-hide-mobile"><code><?php echo htmlspecialchars($u['username']); ?></code></td>
                                    <td class="col-hide-mobile">
                                        <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 20px; 
                                                     background: <?php echo $u['ruolo'] === 'admin' ? 'rgba(239, 68, 68, 0.1); color: var(--danger);' : 'var(--cat-venduto-bg); color: var(--accent-dark);'; ?>">
                                            <?php echo $u['ruolo']; ?>
                                        </span>
                                    </td>
                                    <td class="col-hide-mobile" style="font-weight: 600; color: var(--text-main); text-transform: capitalize;">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $u['nazionalita'])); ?>
                                    </td>
                                    <td class="col-hide-mobile"><?php echo date('d/m/Y', strtotime($u['creato_il'])); ?></td>
                                    <td>
                                        <div style="display:flex; gap:0.5rem;">
                                            <a href="admin.php?tab=agenti&edit=<?php echo $u['id']; ?>" class="btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">
                                                <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i> Modifica
                                            </a>
                                            <?php if ($u['id'] !== $current_user['id']): ?>
                                                <form action="admin.php?tab=agenti" method="POST" onsubmit="return confirm('Sei sicuro?');" style="display:inline;">
                                                    <?php echo get_csrf_input(); ?>
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">
                                                        <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i> Elimina
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <!-- =========================================
             TAB: AUTO
             ========================================= -->
        <?php elseif ($tab === 'auto'): 
            $tutte_auto = [];
            try {
                $stmt = $db->query("SELECT a.*, u.nome_completo, u.username FROM auto a LEFT JOIN utenti u ON a.utente_id = u.id ORDER BY a.id DESC");
                $tutte_auto = $stmt->fetchAll();
            } catch (PDOException $e) {}
        ?>
            <section class="animate-up">
                <?php if ($edit_auto): ?>
                    <div class="premium-card" style="margin-bottom: 2rem; border-top: 4px solid var(--warning);">
                        <div class="card-title">
                            <i data-lucide="edit-3" style="width: 24px; height: 24px;"></i>
                            Modifica Veicolo: <?php echo htmlspecialchars($edit_auto['targa']); ?>
                        </div>
                        <form action="admin.php?tab=auto" method="POST" enctype="multipart/form-data">
                            <?php echo get_csrf_input(); ?>
                            <input type="hidden" name="action" value="edit_auto">
                            <input type="hidden" name="auto_id" value="<?php echo $edit_auto['id']; ?>">

                            <div class="form-group">
                                <label for="modello">Modello Auto *</label>
                                <input type="text" id="modello" name="modello" value="<?php echo htmlspecialchars($edit_auto['modello']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="targa">Targa *</label>
                                <input type="text" id="targa" name="targa" value="<?php echo htmlspecialchars($edit_auto['targa']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="consumo_medio">Consumo Medio km/l *</label>
                                <input type="number" id="consumo_medio" name="consumo_medio" value="<?php echo htmlspecialchars($edit_auto['consumo_medio']); ?>" step="0.01" min="0.1" required>
                            </div>

                            <div class="form-group">
                                <label for="utente_id">Associa ad Agente</label>
                                <select id="utente_id" name="utente_id">
                                    <option value="">-- Nessun Agente --</option>
                                    <?php foreach ($agenti as $ag): ?>
                                        <option value="<?php echo $ag['id']; ?>" <?php echo $edit_auto['utente_id'] == $ag['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ag['nome_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="documenti">Aggiorna Documenti (PDF/Immagine)</label>
                                <input type="file" id="documenti" name="documenti" accept="application/pdf,image/*" style="padding:0.5rem; background:rgba(0,0,0,0.2); border-radius:8px; width:100%; color:white;">
                                <?php if ($edit_auto['documenti']): ?>
                                    <span style="font-size: 0.8rem; color: var(--success); display: block; margin-top: 0.5rem;">Documento attuale caricato: <a href="<?php echo htmlspecialchars($edit_auto['documenti']); ?>" target="_blank" style="color:var(--accent-light);">Visualizza</a></span>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
                                <button type="submit" class="btn-primary" style="flex:1;">Salva Modifiche</button>
                                <a href="admin.php?tab=auto" class="btn-secondary" style="flex:1;">Annulla</a>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="data-table-container">
                    <div class="settings-header">
                        <h3 class="settings-title" style="display:flex; align-items:center; gap:0.5rem;"><i data-lucide="car" style="width: 22px; height: 22px;"></i> Parco Auto</h3>
                        <span style="font-size: 0.85rem; font-weight: bold; background: var(--border-color); padding: 0.3rem 0.8rem; border-radius: 20px; color: var(--text-muted);">
                            Totale: <?php echo count($tutte_auto); ?>
                        </span>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Modello</th>
                                <th>Targa</th>
                                <th class="col-hide-mobile">Consumo</th>
                                <th>Agente Associato</th>
                                <th class="col-hide-mobile">Documenti</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tutte_auto as $a): ?>
                                <tr style="<?php echo ($edit_auto && $edit_auto['id'] === $a['id']) ? 'background: rgba(255, 215, 0, 0.08);' : ''; ?>">
                                    <td style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($a['modello']); ?></td>
                                    <td><code><?php echo htmlspecialchars($a['targa']); ?></code></td>
                                    <td class="col-hide-mobile"><?php echo number_format($a['consumo_medio'], 2, ',', '.'); ?> km/l</td>
                                    <td style="font-weight: 600;">
                                        <?php echo $a['nome_completo'] ? htmlspecialchars($a['nome_completo']) : '<span style="color:var(--text-muted); font-style:italic;">Nessuno</span>'; ?>
                                    </td>
                                    <td class="col-hide-mobile">
                                        <?php if ($a['documenti']): ?>
                                            <a href="<?php echo htmlspecialchars($a['documenti']); ?>" target="_blank" class="btn-secondary" style="padding: 0.3rem 0.6rem; font-size: 0.7rem; border-radius: 6px; display:inline-flex; align-items:center; gap:0.3rem;">
                                                <i data-lucide="file-text" style="width:12px; height:12px;"></i> Apri
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.8rem;">Assente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:0.5rem;">
                                            <a href="admin.php?tab=auto&edit=<?php echo $a['id']; ?>" class="btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">
                                                <i data-lucide="edit-2" style="width: 12px; height: 12px;"></i> Modifica
                                            </a>
                                            <form action="admin.php?tab=auto" method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare questa auto?');" style="display:inline;">
                                                <?php echo get_csrf_input(); ?>
                                                <input type="hidden" name="action" value="delete_auto">
                                                <input type="hidden" name="auto_id" value="<?php echo $a['id']; ?>">
                                                <button type="submit" class="btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; border-radius: 8px;">
                                                    <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i> Elimina
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <!-- =========================================
             TAB: IMPOSTAZIONI (CREAZIONE)
             ========================================= -->
        <?php elseif ($tab === 'impostazioni'): ?>
            <section class="animate-up">
                <div class="stats-selector-grid">
                    
                    <!-- Form Creazione Agente -->
                    <div class="premium-card">
                        <div class="card-title">
                            <i data-lucide="user-plus" style="width: 24px; height: 24px;"></i> Crea Nuovo Agente
                        </div>
                        <form action="admin.php?tab=impostazioni" method="POST">
                            <?php echo get_csrf_input(); ?>
                            <input type="hidden" name="action" value="add_user">

                            <div class="form-group">
                                <label for="username">Nome Utente (Username) *</label>
                                <input type="text" id="username" name="username" required autocomplete="username">
                            </div>

                            <div class="form-group">
                                <label for="nome_completo">Nome Completo (Cognome Nome) *</label>
                                <input type="text" id="nome_completo" name="nome_completo" required autocomplete="name">
                            </div>

                            <div class="form-group">
                                <label for="password">Password *</label>
                                <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password">
                                <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600; display: block; margin-top: 0.25rem;">Requisiti: almeno 12 caratteri con lettere maiuscole, minuscole, numeri e simboli.</span>
                            </div>

                            <div class="form-group">
                                <label for="ruolo">Ruolo di Sistema</label>
                                <select id="ruolo" name="ruolo">
                                    <option value="agente" selected>Agente</option>
                                    <option value="admin">Amministratore</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="nazionalita">Nazionalità</label>
                                <select id="nazionalita" name="nazionalita">
                                    <option value="italia">Italia</option>
                                    <option value="spagna">Spagna</option>
                                    <option value="francia">Francia</option>
                                    <option value="belgio">Belgio</option>
                                    <option value="stati_uniti">Stati Uniti</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-primary" style="margin-top: 1.5rem; width: 100%;">
                                <i data-lucide="check" style="width: 18px; height: 18px;"></i> Crea Utente
                            </button>
                        </form>
                    </div>

                    <!-- Form Creazione Auto -->
                    <div class="premium-card">
                        <div class="card-title">
                            <i data-lucide="plus-circle" style="width: 24px; height: 24px;"></i> Registra Nuova Auto
                        </div>
                        <form action="admin.php?tab=impostazioni" method="POST" enctype="multipart/form-data">
                            <?php echo get_csrf_input(); ?>
                            <input type="hidden" name="action" value="add_auto">

                            <div class="form-group">
                                <label for="modello_auto">Modello Auto *</label>
                                <input type="text" id="modello_auto" name="modello" placeholder="Es. Fiat Panda 1.2" required>
                            </div>

                            <div class="form-group">
                                <label for="targa">Targa *</label>
                                <input type="text" id="targa" name="targa" placeholder="Es. GX792SV" required>
                            </div>

                            <div class="form-group">
                                <label for="consumo_medio">Consumo Medio km/l *</label>
                                <input type="number" id="consumo_medio" name="consumo_medio" step="0.01" min="0.1" placeholder="Es. 16.50" required>
                            </div>

                            <div class="form-group">
                                <label for="utente_id">Assegna ad Agente</label>
                                <select id="utente_id" name="utente_id">
                                    <option value="">-- Nessun Agente (Auto libera) --</option>
                                    <?php foreach ($agenti as $ag): ?>
                                        <option value="<?php echo $ag['id']; ?>"><?php echo htmlspecialchars($ag['nome_completo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="documenti">Carica Documenti (PDF/Immagine)</label>
                                <input type="file" id="documenti" name="documenti" accept="application/pdf,image/*" style="padding:0.5rem; background:rgba(0,0,0,0.2); border-radius:8px; width:100%; color:white;">
                            </div>

                            <button type="submit" class="btn-primary" style="margin-top: 1.5rem; width: 100%;">
                                <i data-lucide="check" style="width: 18px; height: 18px;"></i> Crea Auto
                            </button>
                        </form>
                    </div>

                    <!-- Configurazione Prezzi Carburante -->
                    <div class="premium-card">
                        <div class="card-title">
                            <i data-lucide="badge-euro" style="width: 24px; height: 24px;"></i> Prezzi Carburante Nazionali
                        </div>
                        <form action="admin.php?tab=impostazioni" method="POST">
                            <?php echo get_csrf_input(); ?>
                            <input type="hidden" name="action" value="update_settings">
                            
                            <div class="form-group">
                                <label for="prezzo_carburante_italia">Italia (€/l)</label>
                                <input type="number" id="prezzo_carburante_italia" name="prezzo_carburante_italia" value="<?php echo number_format($prezzi_paesi['italia'], 2, '.', ''); ?>" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="prezzo_carburante_spagna">Spagna (€/l)</label>
                                <input type="number" id="prezzo_carburante_spagna" name="prezzo_carburante_spagna" value="<?php echo number_format($prezzi_paesi['spagna'], 2, '.', ''); ?>" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="prezzo_carburante_francia">Francia (€/l)</label>
                                <input type="number" id="prezzo_carburante_francia" name="prezzo_carburante_francia" value="<?php echo number_format($prezzi_paesi['francia'], 2, '.', ''); ?>" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="prezzo_carburante_belgio">Belgio (€/l)</label>
                                <input type="number" id="prezzo_carburante_belgio" name="prezzo_carburante_belgio" value="<?php echo number_format($prezzi_paesi['belgio'], 2, '.', ''); ?>" step="0.01" min="0" required>
                            </div>
                            <div class="form-group">
                                <label for="prezzo_carburante_stati_uniti">Stati Uniti (€/l)</label>
                                <input type="number" id="prezzo_carburante_stati_uniti" name="prezzo_carburante_stati_uniti" value="<?php echo number_format($prezzi_paesi['stati_uniti'], 2, '.', ''); ?>" step="0.01" min="0" required>
                            </div>

                            <button type="submit" class="btn-primary" style="margin-top: 1rem; width: 100%;">
                                <i data-lucide="check" style="width: 18px; height: 18px;"></i> Salva Prezzi
                            </button>
                        </form>
                    </div>

                </div>
            </section>
        <?php endif; ?>

        <!-- ===== LIGHTBOX MODAL (Visualizzazione Foto Contachilometri) ===== -->
        <div id="photo-modal" class="modal">
            <div class="modal-content animate-up">
                <span id="modal-close" class="modal-close">&times;</span>
                <img id="modal-img" class="modal-img" src="" alt="Dettaglio Contachilometri">
                <div id="modal-caption" class="modal-caption"></div>
            </div>
        </div>

    </div>

    <!-- Script JSON dati prezzi paesi -->
    <script id="paesi-prezzi-data" type="application/json">
        <?php echo json_encode($prezzi_paesi); ?>
    </script>

    <script src="app.js?v=<?php echo time(); ?>"></script>
    <script>
        lucide.createIcons();
    </script>
</body>

</html>
