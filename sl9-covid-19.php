<?php
/**
* Plugin Name:          Shoreline COVID 19
* Plugin URI:           https://github.com/shorelinemedia/sl9-covid-19-banner
* Description:          Add a banner to a WP Multisite indicating availability of COVID 19 test kits
* Version:              1.0.0
* Author:               Shoreline Media
* Author URI:           https://shoreline.media
* License:              GNU General Public License v2
* License URI:          http://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:          sl9-covid-19
* GitHub Plugin URI:    https://github.com/shorelinemedia/sl9-covid-19-banner
*/

// Customizer scoped to 'editor' user role to set true/false about test kit availability
if (!function_exists( 'sl9_covid_19_customizer' ) ) {
  function sl9_covid_19_customizer() {}
}
