<?php
// migrate.php - Migrazione per Supabase (PostgreSQL)
require_once __DIR__ . '/config/database.php';

try {
    $db = getDbConnection();
    
    // Convertiamo le colonne BYTEA a TEXT per evitare che PDO le restituisca come resource stream
    $db->exec("ALTER TABLE inserimenti_utente ALTER COLUMN foto_path TYPE TEXT USING encode(foto_path, 'escape');");
    $db->exec("ALTER TABLE auto ALTER COLUMN documenti TYPE TEXT USING encode(documenti, 'escape');");
    echo "Colonne convertite da BYTEA a TEXT con successo.\n";
    
} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
