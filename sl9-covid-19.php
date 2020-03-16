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
  function sl9_covid_19_customizer( $wp_customize ) {

    $wp_customize->add_section( 'sl9_covid_19', array(
      'title' => __( 'COVID 19' ),
      'description' => __( 'Update sitewide settings for the COVID-19 pandemic' ),
      'panel' => '', // Not typically needed.
      'priority' => 160,
      // Authors can access this section
      'capability' => 'edit_published_posts',
      'theme_supports' => '', // Rarely needed.
    ) );

    $wp_customize->add_setting( 'sl9_covid_19_test_kit_status', array(
      'capability' => 'edit_theme_options',
      'sanitize_callback' => 'sl9_covid19_sanitize_checkbox',
    ) );

    $wp_customize->add_control( 'sl9_covid_19_test_kit_status', array(
      'type' => 'checkbox',
      'section' => 'sl9_covid_19', // Add a default or your own section
      'label' => __( 'COVID-19 Test Kits available?' ),
      'description' => __( 'Your website will have a banner indicating the availability of COVID-19 tests.' ),
    ) );

  }
  add_action( 'customize_register', 'sl9_covid_19_customizer' );
}

// Sanitize our checkbox in customizer
if (!function_exists( 'sl9_covid19_sanitize_checkbox' ) ) {
  function sl9_covid19_sanitize_checkbox( $checked ) {
    // Boolean check.
    return ( ( isset( $checked ) && true == $checked ) ? true : false );
  }

}


// Register shortcode assets
if ( !function_exists( 'sl9_covid_19_banner_assets' ) ) {
  function sl9_covid_19_banner_assets() {
    wp_register_style( 'sl9_covid_19_banner', plugins_url( 'assets/css/covid-banner.css', __FILE__ ) );
  }
  add_action( 'wp_enqueue_scripts', 'sl9_covid_19_banner_assets' );
}

// Shortcode to output the banner
if ( !function_exists( 'sl9_covid_19_test_kits_banner_shortcode' ) ) {
  function sl9_covid_19_test_kits_banner_shortcode( $atts = array(), $content = null ) {
       extract(shortcode_atts(array(
          'text' => '',
       ), $atts));

       // Build html
       $html = '';


       // Enqueue styles
       wp_enqueue_style( 'sl9_covid_19_banner' );
       // Get Customizer/theme mod setting for kit status
       $kits_available = get_theme_mod( 'sl9_covid_19_test_kit_status' );
       // Set default text based on customizer checkbox
       $default_text = $kits_available ? 'Yes, we have kits available.' : 'No, we don\'t have kits available.';
       // Use custom text if supplied, or else use default true/false text
       $text = is_main_site() ? 'Please visit our location websites for test kit availability' : ( !empty( $text ) ? $text : $default_text );

       $icon = file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/images/icon-medical-test.svg' );

       // Create CSS class to hook styles to
       $html_class = $kits_available ? 'kits-available' : 'kits-unavailable';

       ob_start();
       // Build the HTML markup below and use the $text variable
       ?>

       <aside role="banner" class="covid-19-banner <?php echo $html_class; ?>">
         <div class="covid-19-banner__icon"><?php echo $icon; ?></div>
         <h2 class="covid-19-banner__title"><strong>Coronavirus Test Kits:</strong> <?php echo $text; ?></h2>
         <?php if ( !is_main_site() ) { ?>
           <a class="btn button" href="/coronavirus-testing-kits/">Learn More</a>
         <?php } // endif is main site ?>
       </aside>

       <?php
       $html .= ob_get_clean();
       return do_shortcode( $html );
  }
  add_shortcode( 'sl9_covid_19_banner', 'sl9_covid_19_test_kits_banner_shortcode', 10, 2 );
}

// Init actions
if ( !function_exists( 'sl9_covid_19_init' ) ) {
  function sl9_covid_19_init() {
    // Hook the shortcode output directly into the template depending on the hooks avaialable
    if ( has_action( 'wp_body_open' ) ) {
      add_action( 'wp_body_open', 'sl9_covid_19_add_banner_to_body' );
    } elseif ( has_action( 'sl9_scriptscodes' ) ) {
      add_action( 'sl9_scriptscodes', 'sl9_covid_19_add_banner_to_body' );
    }
  }
  add_action( 'init', 'sl9_covid_19_init' );
}

// Hook the banner into the body of the site
if ( !function_exists( 'sl9_covid_19_add_banner_to_body' ) ) {
  function sl9_covid_19_add_banner_to_body() {
    echo do_shortcode( '[sl9_covid_19_banner]' );
  }
}
