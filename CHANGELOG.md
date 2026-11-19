# Changelog

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
