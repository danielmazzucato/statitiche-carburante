# Statistiche Carburante MPM

Applicazione interna per la gestione e il monitoraggio delle statistiche sul carburante per gli agenti commerciali di MPM (commerciali esteri e nazionali).

L'applicazione è configurata per l'esecuzione in produzione ed è ospitata online.

---

## 🚀 Infrastruttura Online

L'applicazione utilizza un'architettura cloud moderna e sicura:

1. **Hosting (Frontend/Backend PHP):** Deploiato su **Railway.app** (ambiente Linux/PHP 8.3).
2. **Database PostgreSQL:** Ospitato su **Supabase** (connessione tramite PDO PostgreSQL).
3. **Continuous Deployment (CD):** Collegamento automatico tra il repository GitHub e Railway. Ad ogni `git push` sulla repo, viene avviato il build e il rilascio automatico online.

---

## 🔒 Sicurezza e Protezione Avanzata

Il codice dell'applicazione è stato interamente revisionato e protetto secondo i massimi standard di sicurezza del web:

* **Protezione CSRF (Cross-Site Request Forgery):** Tutti i form sensibili (`login.php`, `agente.php` e tutte le azioni di `admin.php`) utilizzano token di sicurezza univoci generati per sessione.
* **Sessioni Sicure:**
  * Configurate con i flag `HTTPOnly`, `Secure` (rilevamento automatico HTTPS dietro proxy) e `SameSite=Lax` per prevenire il session hijacking.
  * Rigenerazione automatica dell'identificativo di sessione ogni 30 minuti.
  * Protezione tramite fingerprint di sicurezza basato su IP e User Agent dell'utente.
* **Gestione degli IP consapevoli del proxy (Railway):** Rilevamento automatico dell'IP reale del client utilizzando gli header `X-Forwarded-For` e `X-Real-IP` forniti dai load balancer di Railway.
* **Rate Limiting:** Blocco temporaneo dei tentativi di accesso brute force su `login.php` (massimo 5 tentativi in 5 minuti per IP).
* **Sanitizzazione Input:** Tutte le stringhe inserite dagli utenti e i nomi dei file caricati vengono sanitizzati e convalidati prima del salvataggio nel database o sul disco.
* **Nascondimento errori in produzione:** Gli errori interni PHP o SQL sono memorizzati in log protetti sul server e non vengono mai mostrati all'utente finale.

---

## 📁 Struttura Database (Supabase)

Il database PostgreSQL di Supabase contiene le seguenti tabelle:

1. **`utenti`**: Credenziali e anagrafica degli agenti e dell'amministratore (le password sono archiviate in formato hash `bcrypt` ultra-sicuro).
2. **`auto`**: Automobili assegnate agli utenti con relativo consumo medio (km/l), targa e documenti.
3. **`inserimenti_utente`**: Storico dei viaggi registrati dagli agenti commerciali, inclusi chilometraggi e link alle foto del contachilometri.
4. **`configurazioni`**: Parametri di sistema (es. prezzo del carburante per Italia, Spagna, Francia, Belgio, Stati Uniti).

---

## 🛠️ Credenziali e Variabili d'Ambiente

Per motivi di sicurezza, il file `.env` contenente le credenziali reali del database e delle API è escluso dal controllo di versione (`.gitignore`). Le variabili d'ambiente in produzione sono configurate direttamente all'interno della dashboard di **Railway**.

### Variabili richieste nel pannello Railway:
* `DB_HOST`: Host del database Supabase (`db.ftymzpbuqyoxocedauee.supabase.co`)
* `DB_PORT`: Porta di connessione (default `5432`)
* `DB_NAME`: Nome del database (default `postgres`)
* `DB_USER`: Utente di connessione (default `postgres`)
* `DB_PASS`: Password del database Supabase
* `SUPABASE_URL`: Endpoint del progetto Supabase
* `SUPABASE_ANON_KEY`: Chiave pubblica per chiamate anonime
* `SUPABASE_SERVICE_ROLE_KEY`: Chiave privata amministratore (service_role)

---

## 💻 Flusso di Lavoro (Sviluppo & Deploy)

Ad ogni aggiornamento o richiesta:
1. Le modifiche vengono testate e integrate nel codice locale.
2. Viene eseguito un commit ed il push su GitHub.
3. **Railway** rileva la nuova versione su GitHub ed esegue il deploy automatico in meno di un minuto.
