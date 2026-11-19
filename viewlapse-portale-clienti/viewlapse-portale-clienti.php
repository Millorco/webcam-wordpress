<?php
/**
 * Plugin Name:       Viewlapse Portale clienti
 * Plugin URI:        https://github.com/Millorco/webcam-wordpress
 * Description:       Un sottosito riservato per ogni cliente Viewlapse in WordPress Multisite: chiave del cliente per sito, pagine pronte (Webcam, Archivio, Confronto, Dati, Timelapse, Stesso momento, Foto del giorno, Statistiche, Rapporti e certificati), logo e colori del cliente, ruoli "Cliente - visione" e "Cliente - download". Richiede Viewlapse Webcam.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  viewlapse-webcam
 * Network:           true
 * Author:            Viewlapse
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       viewlapse-portale-clienti
 * Domain Path:       /languages
 *
 * @package Viewlapse\Portale
 */

defined( 'ABSPATH' ) || exit;

define( 'VIEWLAPSE_PORTALE_VERSION', '1.1.0' );
define( 'VIEWLAPSE_PORTALE_FILE', __FILE__ );
define( 'VIEWLAPSE_PORTALE_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-keys.php';
require_once __DIR__ . '/includes/class-sites.php';
require_once __DIR__ . '/includes/class-access.php';
require_once __DIR__ . '/includes/class-appearance.php';
require_once __DIR__ . '/includes/class-network.php';
require_once __DIR__ . '/includes/class-portale.php';

register_activation_hook( __FILE__, array( 'Viewlapse\\Portale\\Portale', 'activate' ) );

Viewlapse\Portale\Portale::boot();
