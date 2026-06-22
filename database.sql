-- database.sql
-- Schema per l'applicazione Statistiche Carburante (Versione PostgreSQL per Supabase)

DROP TABLE IF EXISTS inserimenti_utente;
DROP TABLE IF EXISTS auto;
DROP TABLE IF EXISTS utenti;
DROP TABLE IF EXISTS configurazioni;

CREATE TABLE IF NOT EXISTS utenti (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nome_completo VARCHAR(100) NOT NULL,
    ruolo VARCHAR(20) NOT NULL DEFAULT 'agente' CHECK (ruolo IN ('admin', 'agente')),
    nazionalita VARCHAR(50) NOT NULL DEFAULT 'italia' CHECK (nazionalita IN ('italia', 'spagna', 'francia', 'belgio', 'stati_uniti')),
    creato_il TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auto (
    id SERIAL PRIMARY KEY,
    utente_id INT UNIQUE DEFAULT NULL,
    modello VARCHAR(100) NOT NULL,
    targa VARCHAR(50) UNIQUE NOT NULL,
    consumo_medio DECIMAL(5,2) DEFAULT NULL, -- espresso in km/l (es. 15.5)
    documenti BYTEA DEFAULT NULL,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inserimenti_utente (
    id SERIAL PRIMARY KEY,
    utente_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    modello_auto VARCHAR(100) NOT NULL,
    consumo_medio DECIMAL(5,2) NOT NULL,
    data_inserimento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    km INT NOT NULL,
    foto_path BYTEA DEFAULT NULL,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS configurazioni (
    chiave VARCHAR(50) PRIMARY KEY,
    valore VARCHAR(255) NOT NULL
);

-- Inserimento configurazione predefinita per il prezzo del carburante per vari paesi
INSERT INTO configurazioni (chiave, valore) VALUES
('prezzo_carburante_italia', '1.80'),
('prezzo_carburante_spagna', '1.65'),
('prezzo_carburante_francia', '1.85'),
('prezzo_carburante_belgio', '1.75'),
('prezzo_carburante_stati_uniti', '0.95')
ON CONFLICT (chiave) DO UPDATE SET valore = EXCLUDED.valore;

-- Inserimento utenti
INSERT INTO utenti (id, username, password, nome_completo, ruolo, nazionalita) VALUES
(1, 'admin', '$2y$10$rKeuA7sbP1j5UmiQ/LRphup/Wp1WJ.dqvALBftqBqhVC8Rh8oYXF.', 'Amministratore MPM', 'admin', 'italia'),
(2, 'marco.silvestri', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'MARCO SILVESTRI', 'agente', 'italia'),
(3, 'andrea.zanon', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'ANDREA ZANON', 'agente', 'italia'),
(4, 'damiano.trentin', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'DAMIANO TRENTIN', 'agente', 'italia'),
(5, 'quentin.tardy', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'QUENTIN TARDY', 'agente', 'francia'),
(6, 'christophe.nave', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'CHRISTOPHE NAVE', 'agente', 'francia'),
(7, 'anthony.stahl', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'ANTHONY STAHL', 'agente', 'francia'),
(8, 'francisco.ramos', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'FRANCISCO JAVIER RAMOS', 'agente', 'spagna'),
(9, 'pablo.martinez', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'PABLO PASCUAL MARTINEZ', 'agente', 'spagna'),
(10, 'jesus.torres', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'JESUS TORRES', 'agente', 'spagna'),
(11, 'javier.pando', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'JAVIER ALONSO PANDO', 'agente', 'spagna'),
(12, 'enrique.alcantara', '$2y$10$CMcIInmPQ.mf8pDaM8x1Gu19va.Dcf4NEof6Jhx4T2nSKc75W0uY.', 'ENRIQUE ALCANTARA', 'agente', 'spagna')
ON CONFLICT (username) DO UPDATE SET 
    password = EXCLUDED.password,
    nome_completo = EXCLUDED.nome_completo,
    ruolo = EXCLUDED.ruolo,
    nazionalita = EXCLUDED.nazionalita;

-- Reset della sequenza utenti (poiché abbiamo inserito ID manuali)
SELECT setval('utenti_id_seq', (SELECT MAX(id) FROM utenti));

-- Inserimento auto
INSERT INTO auto (utente_id, modello, targa, consumo_medio) VALUES
(2, 'SKODA OCTAVIA WAGON 2,0 TDI 110 KW', 'GX792SV', 20.0),
(3, 'SKODA OCTAVIA WAGON 2,0 TDI 110 KW', 'GX898SV', 20.0),
(4, 'SKODA OCTAVIA WAGON 2,0 TDI 85 KW', 'GN989DH', 21.7),
(5, 'VOLKSWAGEN T-CROSS 133', 'HK900YV', 18.2),
(6, 'OPEL MOKKA UTL HBP AUT', 'HJ515BL', 20.8),
(7, 'SKODA KAMIQ UTL PET AUT', 'HG939TN', 18.2),
(8, 'CITROËN C4 X BlueHDi 130 S&S EAT8 Plus', '9998MWW', 20.8),
(9, 'CITROËN C4 X BlueHDi 130 S&S EAT8 Plus', '9984MWW', 20.8),
(10, 'CITROËN C4 X BlueHDi 130 S&S EAT8 Plus', '1999MXB', 20.8),
(11, 'CITROËN C4 X BlueHDi 130 S&S EAT8 Plus', '9991MWW', 20.8),
(12, 'CITROËN C4 X Hybrid 145 e-DSC6 Plus', '0546NFT', 21.7)
ON CONFLICT (targa) DO UPDATE SET 
    modello = EXCLUDED.modello,
    consumo_medio = EXCLUDED.consumo_medio;
