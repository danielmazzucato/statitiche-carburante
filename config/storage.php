<?php
// config/storage.php
// Helper per Supabase Storage — upload, eliminazione e URL pubblici
// Risolve il problema del filesystem effimero su Railway:
// i file caricati vengono salvati su Supabase Storage (persistente).

/**
 * Assicura che il bucket esista su Supabase Storage.
 * Viene chiamata automaticamente prima di ogni upload.
 */
function _ensureStorageBucket($bucket = 'uploads') {
    static $checked = [];
    if (isset($checked[$bucket])) return;

    $headers = [
        'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        'apikey: ' . SUPABASE_ANON_KEY,
    ];

    // Verifica esistenza bucket
    $ch = curl_init(SUPABASE_URL . '/storage/v1/bucket/' . $bucket);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $checked[$bucket] = true;
        return;
    }

    // Crea il bucket (pubblico, max 5 MB)
    $ch = curl_init(SUPABASE_URL . '/storage/v1/bucket');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'id'     => $bucket,
            'name'   => $bucket,
            'public' => true,
            'file_size_limit'    => 5 * 1024 * 1024,
            'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        ]),
        CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Type: application/json']),
        CURLOPT_TIMEOUT    => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);

    $checked[$bucket] = true;
}

/**
 * Carica un file su Supabase Storage.
 *
 * @param  string $local_path   Percorso locale del file temporaneo (es. $_FILES[...]['tmp_name'])
 * @param  string $filename     Nome file remoto (es. "odometer_6_123_abc.jpeg")
 * @param  string $mime_type    Tipo MIME (es. "image/jpeg")
 * @param  string $bucket       Nome del bucket (default: "uploads")
 * @return string|false         URL pubblico in caso di successo, false in caso di errore
 */
function supabaseStorageUpload($local_path, $filename, $mime_type, $bucket = 'uploads') {
    _ensureStorageBucket($bucket);

    $url = SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . $filename;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => file_get_contents($local_path),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . SUPABASE_ANON_KEY,
            'Content-Type: ' . $mime_type,
            'x-upsert: true',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        return SUPABASE_URL . '/storage/v1/object/public/' . $bucket . '/' . $filename;
    }

    return false;
}

/**
 * Elimina un file da Supabase Storage.
 *
 * @param  string $filename  Nome file remoto (solo il basename, es. "odometer_6_123.jpeg")
 * @param  string $bucket    Nome del bucket
 * @return bool
 */
function supabaseStorageDelete($filename, $bucket = 'uploads') {
    if (!$filename) return false;

    $url = SUPABASE_URL . '/storage/v1/object/' . $bucket;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_POSTFIELDS     => json_encode(['prefixes' => [$filename]]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'apikey: ' . SUPABASE_ANON_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 300;
}

/**
 * Estrae il nome file da un percorso (locale o URL Supabase).
 *
 * "uploads/odometer_6_123.jpeg"                               → "odometer_6_123.jpeg"
 * "https://xxx.supabase.co/storage/v1/object/public/uploads/odometer_6_123.jpeg" → "odometer_6_123.jpeg"
 */
function extractStorageFilename($path) {
    if (!$path) return null;
    return basename($path);
}

/**
 * Verifica se un foto_path è un URL remoto (Supabase Storage) oppure un percorso locale.
 */
function isRemoteUrl($path) {
    return $path && strpos($path, 'http') === 0;
}
