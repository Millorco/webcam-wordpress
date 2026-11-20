# Changelog

## 1.1.0 - 2026-11-19

Servizi che si vendono (fase 3b di Webcam 2.0, API dei siti del portal documento 1.1, contratto 1.3). Compatibile con la 1.0: blocchi, shortcode, attributi e impostazioni esistenti invariati; con un portal 0.3 (API 1.0) i blocchi nuovi restano nascosti ai visitatori.

### Viewlapse Webcam 1.1.0
- Blocchi e shortcode nuovi: `viewlapse/timelapse` `[viewlapse_timelapse]` (lettore video con URL firmati, orizzontali e verticali, schede per tipo, elenco), `viewlapse/momento` `[viewlapse_momento]` (stesso momento in giorni diversi, griglia o sfoglia), `viewlapse/fotogiorno` `[viewlapse_fotogiorno]` (foto del giorno, formati per i social, foto del mese), `viewlapse/statistiche` `[viewlapse_statistiche]` e `viewlapse/documenti` `[viewlapse_documenti]` (solo chiave `client`: statistiche, rapporti mensili, certificati e registro delle giornate di maltempo).
- Campo `features`: si mostra solo quello che è nel servizio della webcam; messaggio cortese o blocco nascosto (nuova impostazione *Funzioni non incluse*), spiegazione per chi modifica i contenuti; `403 feature_unavailable` e `scope_client_required` trattati come risposte definitive.
- Beacon delle visualizzazioni (`view`, `leave`, `play`, `open`) senza cookie, con il solo `Origin`, disattivabile; testo per l'informativa sulla privacy.
- Foto attuale: sponsor e infopoint cliccabili (funzione `sponsor`); webcam `archived` con la data di fine dell'archivio, senza aggiornamento automatico.
- Download di video e documenti solo per chi ha i permessi (`vl_download_original`, `vl_download_documents`); filtri `viewlapse_webcam_can_download_docs`, `viewlapse_webcam_feature_message`, `viewlapse_webcam_docs_denied_message`, `viewlapse_webcam_beacon`.
- *Prova connessione* dice se il portal ha l'API 1.1 e se produce timelapse (`whoami.server`).
- Cache: 10 minuti per i servizi; copia vecchia degli elenchi dei documenti al più 6 ore (URL firmati validi un giorno).

### Viewlapse Portale clienti 1.1.0
- Pagine nuove Timelapse, Stesso momento, Foto del giorno, Statistiche, Rapporti e certificati; aggiunte anche ai sottositi creati con la 1.0 senza toccare le pagine esistenti.
- Capacità `vl_download_documents` per *Cliente - download*, amministratori ed editori: solo loro ricevono i link dei PDF e degli elenchi del registro.
- Messaggi per il cliente ("non è compresa nel servizio della tua webcam, contatta Viewlapse") e per chi non può scaricare.
- *Prova* mostra le funzioni di ogni webcam, le webcam in archivio e avvisa se il portal ha ancora l'API 1.0 o non ha ffmpeg.

## 1.0.0 - 2026-11-19

Prima versione (fase 3 di Webcam 2.0, API dei siti del portal v1, contratto 1.2).

### Viewlapse Webcam 1.0.0
- Impostazioni: indirizzo del portal, chiave facoltativa (mai rimostrata né mandata al browser), aggiornamento della foto, durate della cache, tessere della mappa; *Prova connessione* e *Svuota la cache*.
- Chiamate al portal dal server con `wp_remote_get`, cache nei transient (breve per foto ed elenco, lunga per l'archivio), copia vecchia se il portal non risponde, pausa su `429`.
- Blocchi dinamici senza build e shortcode equivalenti: `viewlapse/foto` `[viewlapse_foto]`, `viewlapse/archivio` `[viewlapse_archivio]`, `viewlapse/confronto` `[viewlapse_confronto]`, `viewlapse/grafico` `[viewlapse_grafico]`, `viewlapse/elenco` `[viewlapse_elenco]`.
- Foto con `srcset` dalle riduzioni del portal, stato, meteo esterno, aggiornamento automatico; archivio con calendario, griglia e visualizzatore a schermo intero; prima/dopo con cursore; grafico SVG in PHP; elenco con mappa Leaflet inclusa (caricata solo se attiva).
- Endpoint REST `viewlapse/v1/render` con contesto firmato e nonce per gli utenti collegati; funziona tutto anche senza JavaScript.
- Stringhe in italiano, `.pot` per altre lingue, `uninstall.php`.

### Viewlapse Portale clienti 1.0.0
- Amministrazione network *Clienti Viewlapse*: portal della rete, creazione del sottosito di un cliente dalla sua chiave `client` (verificata con `whoami`), chiave cifrata per sito, prova, cambio chiave, dominio proprio.
- Pagine create in automatico (Webcam, Archivio, Confronto, Dati) con i blocchi di Viewlapse Webcam.
- Area riservata (pagine, REST, feed, mappe del sito), ruoli *Cliente - visione* e *Cliente - download*, pagina pubblica una per una.
- Logo e colori del cliente dal Personalizza: barra del cliente, blocchi, pagina di login.
