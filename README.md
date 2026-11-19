# webcam-wordpress

Plugin WordPress di Webcam 2.0 (Viewlapse, noleggio di webcam time-lapse). Mostrano nei siti dei clienti le foto e le misure che il [portal](https://github.com/Millorco/webcam-portal) riceve dai router, usando la sua API in sola lettura per i siti ([api-siti-v1.md](https://github.com/Millorco/webcam-portal/blob/claude/project-thread-1a01s5/docs/contratto/api-siti-v1.md), contratto 1.2). Le foto restano sul portal: i siti le mostrano con gli URL firmati che il portal manda, senza salvarle nella libreria media.

| Plugin | Cartella | A cosa serve |
|---|---|---|
| **Viewlapse Webcam** | [`viewlapse-webcam/`](viewlapse-webcam/) | solo visualizzazione, per qualsiasi sito WordPress (hotel, comune, sito del cliente): blocchi e shortcode |
| **Viewlapse Portale clienti** | [`viewlapse-portale-clienti/`](viewlapse-portale-clienti/) | WordPress Multisite con un sottosito riservato per ogni cliente; richiede Viewlapse Webcam |

Requisiti: PHP 8.1 o successivo (provato con 8.2 e 8.3), WordPress 6.4 o successivo. Nessuna dipendenza da composer o npm: i blocchi sono dinamici (block.json e render in PHP) con un editor in JavaScript semplice, senza build. Leaflet 1.9.4 (licenza BSD-2) è incluso nel plugin e si carica solo nei blocchi con la mappa attiva.

## Installazione

Gli zip installabili si fanno con

```sh
./build.sh            # crea dist/viewlapse-webcam-1.0.0.zip e dist/viewlapse-portale-clienti-1.0.0.zip
./build.sh /percorso  # li crea in un'altra cartella
```

poi in WordPress *Plugin → Aggiungi nuovo → Carica plugin*. In alternativa si copia la cartella del plugin in `wp-content/plugins/`. Gli zip non sono nel repository.

## Viewlapse Webcam (solo visualizzazione)

1. Attiva il plugin e apri *Impostazioni → Viewlapse Webcam*.
2. **Indirizzo del portal**: `https://portal.viewlapse.com` (in anteprima XAMPP `http://localhost/webcam-portal`).
3. **Chiave API del sito** (facoltativa): senza chiave si vedono tutte le webcam pubbliche; con una chiave `public` del cliente solo le webcam pubbliche di quel cliente. Una chiave `client` mostra anche le webcam private a chiunque visiti il sito: va usata solo con il portale clienti o in un sito già riservato. Le chiavi si creano nel portal (*Clienti → scheda del cliente → Chiavi API dei siti*, oppure `php bin/site-key.php --crea --cliente 7 --scope public`); il plugin chiama il portal dal server, quindi le origini della chiave possono restare vuote.
4. **Prova connessione** mostra scope, cliente e numero di webcam visibili. La chiave non viene mai rimostrata (solo l'inizio, `vls_Obycxu0N…`) e non arriva mai al browser.
5. Cache (transient): 60 s per la foto attuale e l'elenco, 6 ore per l'archivio dei giorni passati, 10 minuti per il grafico. Se il portal non risponde si usa l'ultima risposta ricevuta (fino a 2 giorni: gli URL delle immagini valgono almeno 7). Un `429` del portal sospende le chiamate per il tempo di `Retry-After`. *Svuota la cache* le rifà subito.

### Blocchi e shortcode

Nell'editor i blocchi sono nella categoria **Viewlapse Webcam**. Ogni blocco ha lo shortcode equivalente con gli stessi attributi (stessi nomi di `block.json`).

| Blocco | Shortcode | Attributi (predefinito) |
|---|---|---|
| `viewlapse/foto` — foto attuale | `[viewlapse_foto]` | `webcam` (vuoto = la prima), `scelta` (no: il visitatore sceglie la webcam), `aggiorna` (-1 = impostazione del sito, minuti, 0 = mai), `dimensione` (1920), `titolo`, `ora`, `meteo`, `stato` (sì) |
| `viewlapse/archivio` — archivio per giorno | `[viewlapse_archivio]` | `webcam`, `scelta`, `colonne` (4), `ogni` (0 = tutte le foto; minuti per una foto ogni N) |
| `viewlapse/confronto` — prima/dopo | `[viewlapse_confronto]` | `webcam`, `scelta`, `a` (vuoto = primo giorno dell'archivio), `b` (vuoto = adesso), `scegli` (sì: il visitatore cambia le date), `dimensione` (1920) |
| `viewlapse/grafico` — temperatura e umidità | `[viewlapse_grafico]` | `webcam`, `scelta`, `giorni` (2), `campi` (`temperatura,umidita`), `scegli` (sì: 24 ore, 48 ore, 7 e 30 giorni), `altezza` (260) |
| `viewlapse/elenco` — elenco e mappa | `[viewlapse_elenco]` | `foto` (sì), `colonne` (3), `link` (modello con `{slug}`, es. `/webcam/?vl_cam={slug}`), `mappa` (no), `altezza` della mappa (360) |

Date di `a` e `b`: `AAAA-MM-GG` (mezzogiorno) o `AAAA-MM-GGTHH:MM`, nel fuso della webcam. Booleani negli shortcode: `si`/`no`, `1`/`0`, `true`/`false`.

Esempi:

```text
[viewlapse_foto webcam="monte-cavo" aggiorna="5"]
[viewlapse_archivio webcam="monte-cavo" colonne="6" ogni="60"]
[viewlapse_confronto webcam="monte-cavo" a="2026-08-21" b="2026-11-19T12:00" scegli="no"]
[viewlapse_grafico webcam="monte-cavo" giorni="7" campi="temperatura"]
[viewlapse_elenco mappa="si" link="/webcam/?vl_cam={slug}"]
```

Cosa fanno:

- **foto**: `<img>` con `srcset` dalle riduzioni 320/640/1280/1920 del portal (larghezze reali calcolate dalle dimensioni della foto), stato (*In linea*, *In ritardo*, *Non in linea*...), data e ora nel fuso della webcam, temperatura e umidità esterne (ultimo campione, o quelle della foto). Si aggiorna da sola ogni N minuti (solo quando la scheda è visibile), scaricando la foto nuova prima di sostituirla.
- **archivio**: calendario del mese con i giorni che hanno foto, griglia delle foto del giorno, visualizzatore a schermo intero con frecce, tastiera (← → Esc), scorrimento sul telefono e link all'originale per chi può scaricarlo.
- **confronto**: due foto sovrapposte con il cursore (anche da tastiera); date fisse o scelte dal visitatore.
- **grafico**: SVG generato in PHP con le medie orarie (fascia minima–massima della temperatura, umidità tratteggiata), interrotto dove mancano dati; nessuna libreria JavaScript.
- **elenco**: schede con ultima foto e stato; con `mappa` una mappa Leaflet con i segnaposti delle webcam che hanno coordinate. Le tessere vengono da OpenStreetMap (URL e attribuzione modificabili nelle impostazioni).

Tutto funziona anche senza JavaScript: giorni, mesi, webcam e date sono link e moduli con parametri `vl_cam`, `vl_date`, `vl_month`, `vl_a`, `vl_b`, `vl_giorni` nell'URL della pagina. Con JavaScript lo script del plugin (senza dipendenze) li intercetta e ridisegna solo il blocco con l'endpoint REST interno `GET /wp-json/viewlapse/v1/render`. Ogni blocco porta un contesto firmato con i sali di WordPress (tipo e attributi del blocco): l'endpoint ridisegna solo blocchi pubblicati dal sito, quindi non si può usare la chiave del sito per leggere altre webcam. Gli utenti collegati mandano il nonce `wp_rest`. `GET /wp-json/viewlapse/v1/webcams` (solo per chi modifica i contenuti) dà all'editor l'elenco delle webcam.

### Stili

`assets/css/viewlapse-webcam.css` è minimo: una classe per regola e variabili CSS. Nel tema (o in *Personalizza → CSS aggiuntivo*):

```css
.vl-block { --vl-accent: #c2410c; --vl-radius: 0; --vl-temp: #b91c1c; --vl-hum: #0369a1; }
```

Le disposizioni (calendario accanto alla griglia, colonne) dipendono dalla larghezza del blocco, non dello schermo.

### Lingua

Le stringhe sono scritte in italiano direttamente nel codice (text domain `viewlapse-webcam`): non serve nessun file di traduzione per i siti italiani. `languages/*.pot` serve per tradurle in altre lingue (si rigenera con `wp i18n make-pot viewlapse-webcam viewlapse-webcam/languages/viewlapse-webcam.pot --exclude=assets/vendor --skip-block-json`). Date e mesi usano la lingua del sito WordPress.

### Filtri per gli sviluppatori

| Filtro | Uso |
|---|---|
| `viewlapse_webcam_settings` | cambia portal e chiave per sito (lo usa il portale clienti) |
| `viewlapse_webcam_settings_locked`, `viewlapse_webcam_show_settings` | impostazioni gestite da altri / pagina nascosta |
| `viewlapse_webcam_can_download` | chi vede il link all'originale (predefinito: capacità `vl_download_original`) |
| `viewlapse_webcam_context_public`, `viewlapse_webcam_rest_allowed` | area riservata per l'endpoint REST |

## Viewlapse Portale clienti (Multisite)

Un sottosito per ogni cliente, riservato ai suoi utenti, con le pagine già pronte.

1. Rete WordPress Multisite (a sottocartelle o a sottodomini). Carica i due plugin e **attivali in rete** (*Amministrazione network → Plugin → Attiva sulla rete*): prima Viewlapse Webcam, poi il Portale clienti.
2. *Amministrazione network → Clienti Viewlapse*: imposta l'**indirizzo del portal** (vale per tutti i sottositi).
3. Nel portal crea la chiave del cliente con scope `client` e origini vuote: `php bin/site-key.php --crea --cliente 7 --scope client --etichetta "portale clienti"` (o dal pannello *Clienti*).
4. **Nuovo sottosito cliente**: incolla la chiave, scegli l'indirizzo (`impresa-rossi` → `https://clienti.viewlapse.com/impresa-rossi/`), il titolo (vuoto = nome del cliente nel portal) e, se vuoi, l'email del primo utente con il ruolo. Il plugin verifica la chiave con `whoami` (deve essere `client` e di un cliente), crea il sito e:
   - salva la chiave nelle opzioni del sottosito, **cifrata** (sodium, con una chiave ricavata dai sali di `wp-config.php`); non torna mai in nessuna pagina, si vede solo l'inizio. Se si cambiano i sali va reinserita (*Cambia chiave*);
   - crea le pagine **Webcam** (foto attuale con scelta della webcam ed elenco), **Archivio**, **Confronto** e **Dati** con i blocchi di Viewlapse Webcam; Webcam è la prima pagina;
   - crea i ruoli **Cliente - visione** (vede tutto) e **Cliente - download** (in più scarica l'originale); amministratori ed editori del sito hanno entrambe le capacità;
   - chiude commenti, registrazione e indicizzazione.
5. Nella tabella dei sottositi: *Prova* (chiave valida e webcam visibili), *Cambia chiave* (avvisa se la chiave è di un altro cliente), *Dominio proprio*, link a bacheca, utenti e *Logo e colori*.

Nel sottosito:

- **Area riservata**: chi non è collegato va al login; un utente collegato senza ruolo del sito riceve 403. Sono chiusi anche l'API REST di WordPress, i feed, le mappe del sito e XML-RPC. Le foto si vedono solo nelle pagine; il portal conosce soltanto la chiave del cliente.
- **Pagina pubblica**: nell'editor della pagina, pannello *Accesso* → *Pagina pubblica (visibile senza login)*, oppure *Pagine → Rendi pubblica*. I blocchi di quella pagina si aggiornano anche per i visitatori anonimi; se la pagina torna riservata l'aggiornamento anonimo smette subito.
- **Logo e colori**: *Aspetto → Personalizza → Cliente Viewlapse*: logo, colore principale, testo sul colore principale. Valgono per la barra del cliente in cima alle pagine (logo, utente, *Esci*), per i blocchi delle webcam e per la pagina di login (logo del cliente al posto di quello di WordPress, pulsante nel colore del cliente). Il logo diventa anche il logo del sito per il tema.
- **Utenti**: *Utenti → Aggiungi* del sottosito con ruolo *Cliente - visione* o *Cliente - download*. I clienti non vedono la bacheca (solo il profilo, per la password) né la barra di amministrazione.
- Le impostazioni di Viewlapse Webcam del sottosito sono gestite dalla rete e visibili solo al super amministratore.

### Dominio proprio del cliente

WordPress Multisite (dalla 4.5) mappa i domini da solo, senza plugin né `sunrise.php`.

1. **DNS**: il cliente crea un record `A` (o `CNAME`) da `webcam.cliente.it` al server di WordPress.
2. **Server web**: il dominio deve arrivare allo stesso WordPress. Con Nginx Proxy Manager si aggiunge `webcam.cliente.it` all'host proxy di WordPress (o un host nuovo verso lo stesso container) con il certificato Let's Encrypt; con Apache un `ServerAlias webcam.cliente.it` nel VirtualHost.
3. **WordPress**: *Clienti Viewlapse → Dominio proprio* → `webcam.cliente.it` → *Usa* (equivale ad *Amministrazione network → Siti → Modifica → Indirizzo del sito (URL)* `https://webcam.cliente.it` con percorso `/`; il plugin aggiorna anche `home` e `siteurl`).
4. In `wp-config.php` **non** impostare `COOKIE_DOMAIN` al dominio della rete (lasciarlo non definito): ogni dominio ha i suoi cookie e il login avviene sul dominio del cliente. Il super amministratore accede al sottosito con il login del dominio del cliente.
5. Il vecchio indirizzo (`/impresa-rossi/`) smette di funzionare; i link interni delle pagine sono relativi e continuano a funzionare.

## Verifica

Provato con WordPress 6.5.5 (singolo e Multisite a sottocartelle), PHP 8.3, MariaDB 10.11 e il portal 0.3 di `webcam-portal` riempito con `tools/simula-router.php`: HTML e URL firmati delle immagini dei cinque blocchi e degli shortcode con chiave `public`, senza chiave, con chiave `client`; webcam non visibili (`Webcam non trovata`), date fisse del confronto, copia vecchia con il portal spento; area riservata con e senza login (302 al login, 403 per un utente di un altro sito, 401 su `/wp-json/`), ruolo download (link all'originale solo a lui), pagina pubblica, contesto REST falsificato (400), dominio proprio con login sul nuovo dominio; editor dei blocchi senza errori; schermate con Playwright (desktop e telefono, visualizzatore, cursore, cambio di giorno e di webcam senza ricaricare, aggiornamento automatico). `php -l` su tutti i file.

## Struttura

```
viewlapse-webcam/
  viewlapse-webcam.php      intestazione e avvio
  includes/                 Settings, Api (wp_remote_get + transient), Context (firma), Format,
                            Chart (SVG), Render (HTML dei blocchi), Blocks, Shortcodes, Rest, Admin
  blocks/*/block.json       i cinque blocchi (dinamici)
  assets/                   css, js (frontend, editor, mappa), vendor/leaflet
  languages/                .pot
  uninstall.php
viewlapse-portale-clienti/
  viewlapse-portale-clienti.php
  includes/                 Keys (chiave cifrata), Sites (creazione, pagine, ruoli), Access (area
                            riservata, pagina pubblica), Appearance (Personalizza, barra, login), Network
  assets/                   css della barra, js del pannello "Accesso"
  languages/, uninstall.php
build.sh                    zip installabili
```
