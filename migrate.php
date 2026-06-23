<?php
// migrate.php - Migrazione per Supabase (PostgreSQL)
require_once __DIR__ . '/config/database.php';

try {
    $db = getDbConnection();
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage() . "\n");
}

echo "Inizio migrazioni...\n\n";

// 1. Conversione colonne (se sono BYTEA, altrimenti falliscono silenziosamente o con nota)
try {
    $db->exec("ALTER TABLE inserimenti_utente ALTER COLUMN foto_path TYPE TEXT USING encode(foto_path, 'escape');");
    echo "OK: Colonna foto_path convertita da BYTEA a TEXT.\n";
} catch (PDOException $e) {
    echo "INFO: Salto conversione foto_path (probabilmente già di tipo TEXT).\n";
}

try {
    $db->exec("ALTER TABLE auto ALTER COLUMN documenti TYPE TEXT USING encode(documenti, 'escape');");
    echo "OK: Colonna documenti convertita da BYTEA a TEXT.\n";
} catch (PDOException $e) {
    echo "INFO: Salto conversione documenti (probabilmente già di tipo TEXT).\n";
}

// 2. Creazione indici
try {
    $db->exec("CREATE INDEX IF NOT EXISTS idx_inserimenti_utente_id ON inserimenti_utente(utente_id);");
    echo "OK: Indice idx_inserimenti_utente_id creato/verificato.\n";
} catch (PDOException $e) {
    echo "ERRORE nella creazione di idx_inserimenti_utente_id: " . $e->getMessage() . "\n";
}

try {
    $db->exec("CREATE INDEX IF NOT EXISTS idx_inserimenti_data_inserimento ON inserimenti_utente(data_inserimento);");
    echo "OK: Indice idx_inserimenti_data_inserimento creato/verificato.\n";
} catch (PDOException $e) {
    echo "ERRORE nella creazione di idx_inserimenti_data_inserimento: " . $e->getMessage() . "\n";
}

try {
    $db->exec("CREATE INDEX IF NOT EXISTS idx_auto_utente_id ON auto(utente_id);");
    echo "OK: Indice idx_auto_utente_id creato/verificato.\n";
} catch (PDOException $e) {
    echo "ERRORE nella creazione di idx_auto_utente_id: " . $e->getMessage() . "\n";
}

echo "\nMigrazioni completate.\n";

