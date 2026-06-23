/**
 * Statistiche Carburante MPM — Client Logic
 * Tema scuro, anteprime upload, calcoli live ed eventi modal.
 */

// =========================================
// MONITORAGGIO SESSIONE (CHIUSURA APP)
// =========================================
(function() {
    const page = window.location.pathname.split('/').pop();
    
    // Se siamo in area riservata protetta (agente o admin)
    if (page === 'agente.php' || page === 'admin.php') {
        if (!sessionStorage.getItem('mpm_session_active')) {
            // Se sessionStorage è vuoto, vuol dire che l'applicazione è stata chiusa e riaperta
            window.location.href = 'logout.php';
            return;
        }
    }
    
    // Se siamo sul login, intercettiamo il form per salvare l'attivazione della sessione
    if (page === 'login.php') {
        // Rimuoviamo eventuale sessione residua in sessionStorage
        sessionStorage.removeItem('mpm_session_active');
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function() {
                    sessionStorage.setItem('mpm_session_active', 'true');
                });
            }
        });
    }
})();

document.addEventListener('DOMContentLoaded', function () {

    // Inizializza le icone Lucide all'avvio
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // =========================================
    // TEMA SCURO (DARK MODE)
    // =========================================
    const themeToggle = document.getElementById('theme-toggle');

    // Carica la preferenza salvata (in sincronia con la Business Intelligence)
    if (localStorage.getItem('bi-dark-mode') === 'true') {
        document.body.classList.add('dark-mode');
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('bi-dark-mode', document.body.classList.contains('dark-mode'));
        });
    }

    // =========================================
    // ANTEPRIMA IMMAGINE PER AGENTE
    // =========================================
    const fileInput = document.getElementById('foto_contachilometri');
    const previewContainer = document.getElementById('preview-img-container');
    const previewImg = document.getElementById('preview-img');
    const uploadText = document.querySelector('.upload-text');

    if (fileInput && previewContainer && previewImg) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.addEventListener('load', function () {
                    previewImg.setAttribute('src', this.result);
                    previewContainer.style.display = 'flex';
                    if (uploadText) {
                        uploadText.textContent = "Foto selezionata: " + file.name;
                        uploadText.style.color = "var(--success)";
                    }
                });
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
                if (uploadText) {
                    uploadText.textContent = "Carica una foto del contachilometri";
                    uploadText.style.color = "var(--text-main)";
                }
            }
        });
    }

    // =========================================
    // POPUP / LIGHTBOX FOTO PER ADMIN
    // =========================================
    const modal = document.getElementById('photo-modal');
    const modalImg = document.getElementById('modal-img');
    const modalCaption = document.getElementById('modal-caption');
    const modalClose = document.getElementById('modal-close');
    const thumbnails = document.querySelectorAll('.img-thumbnail');

    if (modal && modalImg && modalClose) {
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function () {
                const fullSrc = this.getAttribute('data-full-src');
                const caption = this.getAttribute('data-caption');
                modalImg.setAttribute('src', fullSrc);
                if (modalCaption) {
                    modalCaption.textContent = caption;
                }
                modal.style.display = 'flex';
            });
        });

        // Chiudi il modal al click sulla X o sullo sfondo
        modalClose.addEventListener('click', function () {
            modal.style.display = 'none';
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // =========================================
    // CALCOLO INTERATTIVO PREZZO NELLA DASHBOARD
    // =========================================
    const prezzoInput = document.getElementById('dashboard-prezzo-carburante');
    const costoOutput = document.getElementById('dashboard-costo-stimato');
    const litriValueSpan = document.getElementById('dashboard-litri-value');
    const paeseSelect = document.getElementById('dashboard-paese-prezzo');
    const prezziDataEl = document.getElementById('paesi-prezzi-data');

    if (prezzoInput && costoOutput && litriValueSpan) {
        const litri = parseFloat(litriValueSpan.getAttribute('data-litri'));

        // Gestore per l'aggiornamento del costo totale
        const ricalcolaCosto = function () {
            let prezzo = parseFloat(prezzoInput.value);
            if (isNaN(prezzo) || prezzo < 0) {
                prezzo = 0;
            }
            const costoStimato = litri * prezzo;
            costoOutput.textContent = formatEUR(costoStimato);
        };

        prezzoInput.addEventListener('input', ricalcolaCosto);

        // Selettore del paese
        if (paeseSelect && prezziDataEl) {
            try {
                const prezziPaesi = JSON.parse(prezziDataEl.textContent);
                paeseSelect.addEventListener('change', function () {
                    const paese = this.value;
                    if (prezziPaesi[paese] !== undefined) {
                        prezzoInput.value = parseFloat(prezziPaesi[paese]).toFixed(2);
                        ricalcolaCosto();
                    }
                });
            } catch (e) {
                console.error("Errore nel parsing dei prezzi nazionali:", e);
            }
        }
    }

    /**
     * Formatta come valuta EUR.
     */
    function formatEUR(val) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(val);
    }
});

// =========================================
// REGISTRAZIONE SERVICE WORKER PER PWA
// =========================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('sw.js')
            .then(function (registration) {
                console.log('Service Worker registrato con successo con scope:', registration.scope);
            })
            .catch(function (error) {
                console.error('Registrazione Service Worker fallita:', error);
            });
    });
}

