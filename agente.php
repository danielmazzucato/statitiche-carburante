<?php
// agente.php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

// Assicura che l'utente sia loggato come agente
require_role('agente');

$user = get_logged_user();
if (!$user) {
    header("Location: logout.php");
    exit();
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $km = filter_input(INPUT_POST, 'km', FILTER_VALIDATE_INT);
    $foto_path = null;

    $tipo_data = $_POST['tipo_data'] ?? 'attuale';
    $data_personalizzata = $_POST['data_personalizzata'] ?? '';
    
    $data_inserimento = null;
    if ($tipo_data === 'personalizzata' && !empty($data_personalizzata)) {
        $date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $data_personalizzata);
        if ($date_obj) {
            $data_inserimento = $date_obj->format('Y-m-d H:i:s');
        } else {
            $error_msg = "Formato data personalizzata non valido.";
        }
    } else {
        $data_inserimento = date('Y-m-d H:i:s');
    }

    // Controlla che i km siano validi
    if ($km === false || $km <= 0) {
        $error_msg = "Inserisci un numero di chilometri valido (maggiore di 0).";
    }

    // Gestione caricamento foto
    if (empty($error_msg) && isset($_FILES['foto_contachilometri']) && $_FILES['foto_contachilometri']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['foto_contachilometri'];
        
        // Verifica errori di caricamento
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_msg = "Errore durante il caricamento della foto.";
        } else {
            // Controlla il tipo MIME
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types)) {
                $error_msg = "Formato file non valido. Carica un'immagine JPG, PNG o WebP.";
            } else {
                // Dimensione massima 5MB
                if ($file['size'] > 5 * 1024 * 1024) {
                    $error_msg = "L'immagine è troppo grande. Dimensione massima consentita: 5MB.";
                } else {
                    // Crea nome file unico e sposta nella cartella uploads
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    if (empty($ext)) {
                        $ext = ($mime_type === 'image/png') ? 'png' : (($mime_type === 'image/webp') ? 'webp' : 'jpg');
                    }
                    $filename = 'odometer_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = __DIR__ . '/uploads/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $foto_path = 'uploads/' . $filename;
                    } else {
                        $error_msg = "Impossibile salvare l'immagine caricata.";
                    }
                }
            }
        }
    }

    // Salva nel database se non ci sono errori
    if (empty($error_msg)) {
        try {
            $db = getDbConnection();
            
            // Per sicurezza, verifichiamo se l'utente ha inserito dei dati in anagrafica
            if (empty($user['modello_auto']) || empty($user['consumo_medio'])) {
                $error_msg = "Attenzione: non puoi inserire registrazioni perché il tuo profilo non ha ancora un modello auto o un consumo medio impostato. Contatta l'amministratore.";
            } else {
                $stmt = $db->prepare("INSERT INTO inserimenti_utente (utente_id, username, modello_auto, consumo_medio, km, foto_path, data_inserimento) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $user['id'],
                    $user['username'],
                    $user['modello_auto'],
                    $user['consumo_medio'],
                    $km,
                    $foto_path,
                    $data_inserimento
                ]);
                
                $success_msg = "Registrazione effettuata con successo! I dati del viaggio sono stati memorizzati.";
            }
        } catch (PDOException $e) {
            $error_msg = "Errore durante il salvataggio dei dati: " . $e->getMessage();
        }
    }
}

// Formatta la data odierna per la visualizzazione
$data_corrente = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPM | Area Agente - Inserimento Dati</title>
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
                        <i data-lucide="user" class="subtitle-icon" style="width: 18px; height: 18px;"></i>
                        <span class="mpm-subtitle">Area Agente</span>
                    </div>
                    <h1 class="mpm-title">
                        <span>INSERISCI</span>
                        <span class="title-highlight">DATI AUTO</span>
                    </h1>
                </div>
            </div>

            <div class="header-meta">
                <!-- Tema Toggle -->
                <button id="theme-toggle" class="theme-toggle" title="Cambia Tema">
                    <i class="moon-icon" data-lucide="moon"></i>
                    <i class="sun-icon" data-lucide="sun"></i>
                </button>
                <!-- Logout -->
                <a href="logout.php" class="btn-logout" title="Esci">
                    <i data-lucide="log-out"></i>
                </a>
            </div>
        </header>

        <!-- ===== CONTENUTO PRINCIPALE ===== -->
        <main class="agente-wrapper animate-up animate-delay-1">
            <div class="premium-card">
                <div class="card-title">
                    <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
                    Nuova Registrazione Carburante
                </div>

                <!-- Banners Messaggi -->
                <?php if (!empty($success_msg)): ?>
                    <div class="alert info">
                        <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                        <span><?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert danger">
                        <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                        <span><?php echo htmlspecialchars($error_msg); ?></span>
                    </div>
                <?php endif; ?>

                <form action="agente.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Agente (Nome Profilo)</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['nome_completo']); ?> (<?php echo htmlspecialchars($user['username']); ?>)" readonly>
                    </div>

                    <div class="form-group">
                        <label>Modello Auto Associato</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['modello_auto'] ?? 'Non impostato'); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Targa Auto Associata</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['targa'] ?? 'Non impostata'); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Consumo Medio Registrato (km/l)</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['consumo_medio'] ? number_format($user['consumo_medio'], 2, ',', '.') . ' km/l' : 'Non impostato'); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Nazionalità Profilo</label>
                        <?php 
                        $nazionalita_nomi = [
                            'italia' => 'Italia',
                            'spagna' => 'Spagna',
                            'francia' => 'Francia',
                            'belgio' => 'Belgio',
                            'stati_uniti' => 'Stati Uniti'
                        ];
                        $naz_val = $user['nazionalita'] ?? 'italia';
                        $naz_display = $nazionalita_nomi[$naz_val] ?? ucfirst($naz_val);
                        ?>
                        <input type="text" value="<?php echo htmlspecialchars($naz_display); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Data Inserimento</label>
                        <div class="radio-group" style="display: flex; gap: 1.5rem; margin-bottom: 0.75rem; padding: 0.5rem 0;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="tipo_data" value="attuale" checked onchange="document.getElementById('data_personalizzata_container').style.display='none'">
                                <span>Istantanea (Attuale: <?php echo $data_corrente; ?>)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="tipo_data" value="personalizzata" onchange="document.getElementById('data_personalizzata_container').style.display='block'">
                                <span>Manuale</span>
                            </label>
                        </div>
                        <div id="data_personalizzata_container" style="display: none;">
                            <input type="datetime-local" id="data_personalizzata" name="data_personalizzata" max="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color, rgba(255,255,255,0.1)); background: var(--bg-color, rgba(0,0,0,0.2)); color: inherit; font-family: inherit;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="km">Chilometri Attuali Auto *</label>
                        <input type="number" id="km" name="km" placeholder="Inserisci il numero di chilometri attuali" min="1" step="1" required>
                    </div>

                    <div class="form-group">
                        <label>Foto Contachilometri (Opzionale)</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="foto_contachilometri" name="foto_contachilometri" accept="image/jpeg,image/png,image/webp" capture="environment">
                            <div class="upload-icon"><i data-lucide="camera" style="width: 32px; height: 32px;"></i></div>
                            <div class="upload-text">Seleziona o trascina una foto</div>
                            <div class="upload-hint">Formati supportati: JPG, PNG, WebP (Max 5MB)</div>
                        </div>
                        <div id="preview-img-container">
                            <img id="preview-img" src="" alt="Anteprima Contachilometri">
                        </div>
                    </div>

                    <?php if (!empty($user['modello_auto']) && !empty($user['consumo_medio'])): ?>
                        <button type="submit" class="btn-primary" style="margin-top: 1rem;">
                            <i data-lucide="send" style="width: 20px; height: 20px;"></i>
                            Invia Dati
                        </button>
                    <?php else: ?>
                        <div class="alert warning" style="margin-top: 1rem;">
                            <i data-lucide="alert-triangle" style="width: 20px; height: 20px;"></i>
                            <span>Contatta l'amministratore per completare l'anagrafica del tuo profilo (modello auto e consumo medio) prima di inserire registrazioni.</span>
                        </div>
                    <?php endif; ?>
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
