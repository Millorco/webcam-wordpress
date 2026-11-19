<?php
/**
 * Plugin Name:       Viewlapse Webcam
 * Plugin URI:        https://github.com/Millorco/webcam-wordpress
 * Description:       Mostra le webcam time-lapse Viewlapse (foto attuale, archivio, prima/dopo, grafico, elenco e mappa) con blocchi e shortcode. Le foto restano sul portal Viewlapse.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Viewlapse
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       viewlapse-webcam
 * Domain Path:       /languages
 *
 * @package Viewlapse\Webcam
 */

defined( 'ABSPATH' ) || exit;

define( 'VIEWLAPSE_WEBCAM_VERSION', '1.0.0' );
define( 'VIEWLAPSE_WEBCAM_FILE', __FILE__ );
define( 'VIEWLAPSE_WEBCAM_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-api.php';
require_once __DIR__ . '/includes/class-context.php';
require_once __DIR__ . '/includes/class-format.php';
require_once __DIR__ . '/includes/class-chart.php';
require_once __DIR__ . '/includes/class-render.php';
require_once __DIR__ . '/includes/class-blocks.php';
require_once __DIR__ . '/includes/class-shortcodes.php';
require_once __DIR__ . '/includes/class-rest.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-plugin.php';

Viewlapse\Webcam\Plugin::boot();
