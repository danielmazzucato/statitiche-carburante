<?php
// migrate.php - Migrazione per Supabase (PostgreSQL)
require_once __DIR__ . '/config/database.php';

try {
    $db = getDbConnection();
    
    // PostgreSQL: ADD COLUMN IF NOT EXISTS evita errori se già presente
    $db->exec("ALTER TABLE auto ADD COLUMN IF NOT EXISTS documenti BYTEA DEFAULT NULL;");
    echo "Colonna documenti verificata/aggiunta con successo.\n";
    
} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
