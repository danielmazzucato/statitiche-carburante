<?php
// migrate.php - Migrazione per Supabase (PostgreSQL)
require_once __DIR__ . '/config/database.php';

try {
    $db = getDbConnection();
    
    // Convertiamo le colonne BYTEA a TEXT per evitare che PDO le restituisca come resource stream
    $db->exec("ALTER TABLE inserimenti_utente ALTER COLUMN foto_path TYPE TEXT USING encode(foto_path, 'escape');");
    $db->exec("ALTER TABLE auto ALTER COLUMN documenti TYPE TEXT USING encode(documenti, 'escape');");
    // Crea gli indici per ottimizzare le prestazioni
    $db->exec("CREATE INDEX IF NOT EXISTS idx_inserimenti_utente_id ON inserimenti_utente(utente_id);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_inserimenti_data_inserimento ON inserimenti_utente(data_inserimento);");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_auto_utente_id ON auto(utente_id);");
    echo "Indici database creati/verificati con successo.\n";
    
} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

