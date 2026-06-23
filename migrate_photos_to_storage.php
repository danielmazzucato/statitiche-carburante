<?php
/**
 * migrate_photos_to_storage.php
 * 
 * Migrazione una tantum: carica le foto locali (cartella uploads/) su Supabase Storage
 * e aggiorna i percorsi nel database da percorsi locali a URL pubblici Supabase.
 * 
 * Eseguire UNA VOLTA dopo il deploy:
 *   php migrate_photos_to_storage.php
 * 
 * Oppure accedere via browser:
 *   https://tuodominio.com/migrate_photos_to_storage.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/storage.php';

echo "<pre>\n";
echo "=== MIGRAZIONE FOTO A SUPABASE STORAGE ===\n\n";

$db = getDbConnection();

// 1. Migra foto degli inserimenti (foto_path)
echo "--- Migrazione foto inserimenti ---\n";
try {
    $stmt = $db->query("SELECT id, foto_path FROM inserimenti_utente WHERE foto_path IS NOT NULL AND foto_path != ''");
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $row) {
        $path = $row['foto_path'];
        
        // Salta se è già un URL Supabase
        if (isRemoteUrl($path)) {
            echo "ID {$row['id']}: già migrato (URL). Skip.\n";
            continue;
        }
        
        // Prova a caricare il file locale
        $local_file = __DIR__ . '/' . $path;
        if (!file_exists($local_file)) {
            echo "ID {$row['id']}: file locale NON trovato ($path). Skip.\n";
            continue;
        }
        
        // Rileva MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $local_file);
        finfo_close($finfo);
        
        $filename = basename($path);
        echo "ID {$row['id']}: caricamento $filename su Supabase... ";
        
        $public_url = supabaseStorageUpload($local_file, $filename, $mime);
        
        if ($public_url) {
            // Aggiorna il percorso nel database
            $update = $db->prepare("UPDATE inserimenti_utente SET foto_path = ? WHERE id = ?");
            $update->execute([$public_url, $row['id']]);
            echo "OK → $public_url\n";
        } else {
            echo "ERRORE nell'upload.\n";
        }
    }
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

// 2. Migra documenti auto
echo "\n--- Migrazione documenti auto ---\n";
try {
    $stmt = $db->query("SELECT id, documenti FROM auto WHERE documenti IS NOT NULL AND documenti != ''");
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $row) {
        $path = $row['documenti'];
        
        if (isRemoteUrl($path)) {
            echo "Auto ID {$row['id']}: già migrato (URL). Skip.\n";
            continue;
        }
        
        $local_file = __DIR__ . '/' . $path;
        if (!file_exists($local_file)) {
            echo "Auto ID {$row['id']}: file locale NON trovato ($path). Skip.\n";
            continue;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $local_file);
        finfo_close($finfo);
        
        $filename = basename($path);
        echo "Auto ID {$row['id']}: caricamento $filename su Supabase... ";
        
        $public_url = supabaseStorageUpload($local_file, $filename, $mime);
        
        if ($public_url) {
            $update = $db->prepare("UPDATE auto SET documenti = ? WHERE id = ?");
            $update->execute([$public_url, $row['id']]);
            echo "OK → $public_url\n";
        } else {
            echo "ERRORE nell'upload.\n";
        }
    }
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

echo "\n=== MIGRAZIONE COMPLETATA ===\n";
echo "</pre>\n";
