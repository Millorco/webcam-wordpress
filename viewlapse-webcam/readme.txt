=== Viewlapse Webcam ===
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mostra le webcam time-lapse Viewlapse: foto attuale, archivio per giorno, prima/dopo, grafico di temperatura e umidità, elenco e mappa, timelapse, stesso momento in giorni diversi, foto del giorno; con la chiave "client" statistiche, rapporti mensili e certificati di maltempo.

== Description ==

Blocchi `viewlapse/foto`, `viewlapse/archivio`, `viewlapse/confronto`, `viewlapse/grafico`, `viewlapse/elenco`, `viewlapse/timelapse`, `viewlapse/momento`, `viewlapse/fotogiorno`, `viewlapse/statistiche`, `viewlapse/documenti` e gli shortcode equivalenti (`[viewlapse_foto]`... `[viewlapse_documenti]`).
Ogni blocco dei servizi mostra solo le funzioni del servizio della webcam (campo `features` del portal); con un portal che non le offre ancora i blocchi restano nascosti ai visitatori.
Statistiche delle visualizzazioni senza cookie e senza dati personali (disattivabili nelle impostazioni).
Le chiamate al portal partono dal server (la chiave non arriva al browser), con cache nei transient. Le foto restano sul portal: nessun file nella libreria media.
Configurazione in Impostazioni → Viewlapse Webcam. Documentazione completa nel README del repository.

Leaflet 1.9.4 (BSD-2-Clause) è incluso in assets/vendor/leaflet.

== Changelog ==

= 1.1.0 =
Servizi dell'API dei siti 1.1: timelapse (anche verticali), stesso momento, foto del giorno con i formati per i social, sponsor e infopoint, statistiche, rapporti e certificati, beacon delle visualizzazioni, webcam in archivio.

= 1.0.0 =
Prima versione.
