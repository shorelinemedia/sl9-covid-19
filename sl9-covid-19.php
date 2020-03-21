<?php
/**
* Plugin Name:          Shoreline COVID 19
* Plugin URI:           https://github.com/shorelinemedia/sl9-covid-19
* Description:          Add a banner to a WP Multisite indicating availability of COVID 19 test kits
* Version:              1.0.11
* Author:               Shoreline Media
* Author URI:           https://shoreline.media
* License:              GNU General Public License v2
* License URI:          http://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:          sl9-covid-19
* GitHub Plugin URI:    https://github.com/shorelinemedia/sl9-covid-19
*/

define( 'SL9_COVID_19_PATH', plugin_dir_path( __FILE__ ) );

// ACF Fields for the main site
if ( is_main_site() ) {
  include( SL9_COVID_19_PATH . 'inc/custom-fields.php' );
}

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
      'capability' => 'edit_published_posts',
      'sanitize_callback' => 'sl9_covid19_sanitize_checkbox',
    ) );

    $wp_customize->add_control( 'sl9_covid_19_test_kit_status', array(
      'type' => 'checkbox',
      'section' => 'sl9_covid_19', // Add a default or your own section
      'label' => __( 'COVID-19 Test Kits available?' ),
      'description' => __( 'Your website will have a banner indicating the availability of COVID-19 tests.' ),
    ) );

    // Testing hours
    $wp_customize->add_setting( 'sl9_covid_19_testing_hours', array(
      'capability' => 'edit_published_posts'
    ) );

    $wp_customize->add_control( 'sl9_covid_19_testing_hours', array(
      'type' => 'text',
      'section' => 'sl9_covid_19', // Add a default or your own section
      'label' => __( 'Hours offering testing' ),
      'description' => __( 'Ex: 9AM - 5PM' ),
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
       // Testing Hours
       $testing_hours  = get_theme_mod( 'sl9_covid_19_testing_hours', false );
       $testing_time = $testing_hours ? 'today from ' . $testing_hours : 'Today';
       // Set default text based on customizer checkbox
       $default_text = $kits_available ? 'Coronavirus Testing <strong>Available ' . $testing_time . '!</strong> ' : 'Coronavirus Testing is <strong>not available</strong> at this time, please check back tomorrow';
       // Use custom text if supplied, or else use default true/false text
       $text = is_main_site() ? '<strong>Coronavirus Testing Now Available:</strong> See our locations below to preregister' : ( !empty( $text ) ? $text : $default_text );

       $icon = file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/images/icon-medical-test.svg' );

       // Create CSS class to hook styles to
       $html_class = $kits_available ? 'kits-available' : 'kits-unavailable';

       ob_start();
       // Build the HTML markup below and use the $text variable
       ?>

       <aside role="banner" class="covid-19-banner <?php echo $html_class; ?>">
         <div class="covid-19-banner__icon"><?php echo $icon; ?></div>
         <h2 class="covid-19-banner__title"><?php echo $text; ?></h2>
         <?php if ( !is_main_site() && $kits_available ) {
           $button_text = $kits_available ? 'Learn More and Preregister' : 'Learn More';
         ?>
           <a class="btn button" href="/coronavirus-testing/"><?php echo $button_text; ?></a>
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

// Location kit availability section
if ( !function_exists( 'sl9_coronavirus_test_kits_availability' ) ) {
  function sl9_coronavirus_test_kits_availability( $post_id = false ) {
    if ( !$post_id ) return;
    $kits_available = get_field( 'coronavirus_test_kits_available', $post_id );
    $location_url = trailingslashit(get_field( 'visit_location', $post_id )['url']);
    $html_class = $kits_available ? 'kits-available' : 'kits-unavailable';
    $text = $kits_available ? 'Coronavirus Testing <strong>Available!</strong><br/><a href="' . $location_url . 'coronavirus-testing/" class="btn button btn-primary">Preregister Now</a>' : 'Coronavirus Testing <strong>is not available</strong> at this time, please check back tomorrow';
    ?>

    <div class="location-kit-availability <?php echo $html_class; ?>">
      <?php echo $text; ?>
    </div>
    <?php

  }
  add_action( 'sl9_home_after_location', 'sl9_coronavirus_test_kits_availability', 10, 1 );

}
