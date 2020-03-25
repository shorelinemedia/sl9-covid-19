<?php
/**
* Plugin Name:          Shoreline COVID 19
* Plugin URI:           https://github.com/shorelinemedia/sl9-covid-19
* Description:          Add a banner to a WP Multisite indicating availability of COVID 19 test kits
* Version:              1.0.13
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

    // Location sites only
    if ( !is_main_site() ) {

      $location = sl9_covid_19_get_location();

      if ( $location ) {
        $location_edit_url = network_site_url( 'wp-admin/post.php?post=' . $location['ID'] . '&action=edit' );
        $wp_customize->add_setting('sl9_covid_19_disabled_message', array(
          'capability' => 'edit_published_posts',
          'transport'   => 'refresh'
        ));

        $wp_customize->add_control( new WP_Customize_Message_Control (
          $wp_customize,
          'sl9_covid_19_disabled_message',
          array(
            'section' => 'sl9_covid_19', // Add a default or your own section
            'label' => __( 'Updating testing availability has moved' ),
            'description' => __( '<p><a class="button button-secondary" href="' . $location_edit_url . '">Please edit the ' . $location['post_title']  . ' location.</a></p>' ),
          )));

      }

    } // endif is not main site

    if ( is_main_site() ) {

      // Testing schedule
      $wp_customize->add_setting( 'sl9_covid_19_location_schedule', array(
        'capability' => 'edit_published_posts',
        'sanitize_callback' => 'wp_kses_post',
        'transport'   => 'refresh'
      ) );

      $wp_customize->add_control( 'sl9_covid_19_location_schedule', array(
        'type' => 'textarea',
        'section' => 'sl9_covid_19', // Add a default or your own section
        'label' => __( 'HTML for the location schedule' ),
        'description' => __( 'Paste in HTML to that shows the location schedule table' ),
      ) );

    } // endif is main site

  }
  add_action( 'customize_register', 'sl9_covid_19_customizer' );
}

// Get ACF custom fields individually since get_fields() breaks in multisite
if ( !function_exists( 'sl9_covid_19_get_custom_fields' ) ) {
  function sl9_covid_19_get_custom_fields( $post_id = '' ) {
    if ( !$post_id ) $post_id = $this->get_location_id();
    if ( !function_exists( 'get_fields' ) ) return false;

    $default_field_names = array(
      'coronavirus_test_kits_available',
      'coronavirus_testing_hours_today',
      'visit_location',
      'hours_of_operation',
      'phone_number',
      'address_line_1',
      'address_line_2',
      'pay_bill'
    );
    // Let themes override which acf fields to get
    $field_names = apply_filters( 'sl9_covid_19_acf_location_fields', array() );
    $field_names = array_merge( $default_field_names, $field_names );
    $fields = array();

    foreach ( $field_names as $field ) {
      $fields[$field] = get_field( $field, $post_id );
    }

    return $fields;
  }
}

// Get locations from main sites' locations post type
if ( !function_exists( 'sl9_covid_19_get_locations' ) ) {
  function sl9_covid_19_get_locations() {
    global $blog_id;

    if ( false === ( $locations = get_site_transient( 'sl9_covid_19_locations' ) ) ) {

      $query_args = array(
        'post_type' => 'locations',
        'posts_per_page' => -1,
        'post_status' => 'publish'
      );

      // Switch to main site to get locations
      switch_to_blog(1);

      $locations = new WP_Query( $query_args );
      $locations = $locations->posts;

      if ( empty( $locations ) ) return false;

      $locations_new = array();
      // Loop through each location and update the post object with the post meta
      foreach ( $locations as $loc ) {

        // Get post meta
        $post_meta = sl9_covid_19_get_custom_fields( $loc->ID );
        $new_key = $loc->post_name;
        // Convert post object to array
        $locations_new[$new_key] = (array) $loc;
        // Add post meta to array
        if ( !empty( $post_meta ) ) {
          $locations_new[$new_key] = array_merge( $locations_new[$new_key], $post_meta );
        }

      }
      $locations = $locations_new;

      restore_current_blog();


      // Update transient for one hour
      set_site_transient( 'sl9_covid_19_locations', $locations, 3600 );


    }


    return $locations;
  }
}

// Pluck a location by slug or id from our locations array
if ( !function_exists( 'sl9_covid_19_get_location' ) ) {
  function sl9_covid_19_get_location( $location = '' ) {
    // Get all locations
    $locations = sl9_covid_19_get_locations();

    // Check if its a string or an integer
    if ( is_numeric( $location ) ) {
      // It's a post ID so try to match to the array key for 'ID'
      foreach ( $locations as $name => $val ) {
        if ( $location == $val['ID'] ) return $locations[$name];
      }
    // If $location is not empty and it's not an integer than it's a string
    } elseif ( !empty( $location ) ){
      // Its a slug so just return the $key
      return $locations[$location];
    }

    // If we have no $location var and it's not the main site, try to match to the
    // site/blog URL
    if ( empty( $location ) && !is_main_site() ) {

      // Get site url
      $site_url = get_site_url();

      // Visit location custom field
      foreach ( $locations as $loc => $val ) {
        $array_search = preg_grep( "/" . preg_quote($site_url, '/') . "/i", $val['visit_location'] );

        if ( !empty( $array_search ) ) {
          return $locations[$loc];
        }

      }


    }
    return false;
  }
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
       $html = $testing_hours = $kits_available = '';

       $is_main_site = is_main_site();


       // Enqueue styles
       wp_enqueue_style( 'sl9_covid_19_banner' );
       // Get the location
       $location = sl9_covid_19_get_location();

       if ( !empty( $location ) ) {
         $kits_available = $location['coronavirus_test_kits_available'];
         $testing_hours = $location['coronavirus_testing_hours_today'];
       } else { return false; }

       $testing_time = !empty( $testing_hours ) ? 'today from ' . $testing_hours : 'Today';
       // Set default text based on customizer checkbox
       $default_text = !empty( $kits_available ) ? 'Coronavirus Testing <strong>Available ' . $testing_time . '!</strong> ' : 'Coronavirus Testing is <strong>not available</strong> at this time, please check our other locations';
       // Use custom text if supplied, or else use default true/false text
       $text = $is_main_site ? '<strong>Coronavirus Testing Now Available:</strong> See our locations below to preregister' : ( !empty( $text ) ? $text : $default_text );

       $icon = file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/images/icon-medical-test.svg' );

       // Create CSS class to hook styles to
       $html_class = $kits_available ? 'kits-available' : 'kits-unavailable';
       $html_class .= $is_main_site ? ' covid-19-banner--main-site' : '';

       ob_start();
       // Build the HTML markup below and use the $text variable
       ?>

       <aside role="banner" class="covid-19-banner <?php echo $html_class; ?>">
         <div class="covid-19-banner__icon"><?php echo $icon; ?></div>
         <h2 class="covid-19-banner__title"><?php echo $text; ?></h2>
         <?php if ( !$is_main_site ) {
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
    // Custom message customizer control
    include_once( SL9_COVID_19_PATH . 'inc/class-wp-customize-message-control.php' );

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
    $location = sl9_covid_19_get_location( $post_id );
    $kits_available = $location['coronavirus_test_kits_available'];
    $location_url = trailingslashit($location['visit_location']['url']);
    $html_class = !empty( $kits_available ) ? 'kits-available' : 'kits-unavailable';
    $text = !empty( $kits_available ) ? 'Coronavirus Testing <strong>Available!</strong><a href="' . $location_url . 'coronavirus-testing/" class="btn button btn-primary">Preregister Now</a>' : 'Coronavirus Testing <strong>is not available</strong> at this time, please check our other locations<a href="' . $location_url . 'coronavirus-testing/" class="btn button btn-primary">Learn more</a>';
    ?>

    <div class="location-kit-availability <?php echo $html_class; ?>">
      <?php echo $text; ?>
    </div>
    <?php

  }
  add_action( 'sl9_home_after_location', 'sl9_coronavirus_test_kits_availability', 10, 1 );

}

// Output the location schedule table
if ( !function_exists( 'sl9_covid_19_location_schedule' ) ) {
  function sl9_covid_19_location_schedule() {
    extract(shortcode_atts(array(
       'text' => '',
    ), $atts));

    if ( false === ( $location_schedule = get_site_transient( 'sl9_covid_19_location_schedule' ) ) ) {

      // Get the theme mod from the main site
      if ( !is_main_site() ) {
        switch_to_blog(1);
        $location_schedule = get_theme_mod( 'sl9_covid_19_location_schedule', '' );
        restore_current_blog();
      } else {
        $location_schedule = get_theme_mod( 'sl9_covid_19_location_schedule', '' );
      }

      // Update transient for one hour
      set_site_transient( 'sl9_covid_19_location_schedule', $location_schedule, 3600 );
    }


    // Build HTML
    $html = '';

    if ( !empty( $location_schedule ) ) {
      wp_enqueue_style( 'sl9_covid_19_banner' );
      $html .= '<div class="sl9-covid-19-location-schedule">';
      $html .= '<h2>Coronavirus (COVID-19) Testing Locations</h2>';
      $html .= $location_schedule;
      $html .= '</div>';
    }


    return do_shortcode( $html );

  }
  add_shortcode( 'sl9_covid_19_location_schedule', 'sl9_covid_19_location_schedule' );
}


// Delete site transients
if ( !function_exists( 'sl9_covid_19_delete_site_transients' ) ) {
  function sl9_covid_19_delete_site_transients() {
    delete_site_transient( 'sl9_covid_19_locations' );
    delete_site_transient( 'sl9_covid_19_location_schedule' );
  }
}

// Clear transients when Customizer is saved
if ( !function_exists( 'sl9_covid_19_customizer_save_after' ) ) {
  function sl9_covid_19_customizer_save_after( $wp_customize ) {
    // Delete site transients
    sl9_covid_19_delete_site_transients();
  }
  add_action( 'customize_save_after', 'sl9_covid_19_customizer_save_after' );
}

// Clear transients when location post types are saved
if ( !function_exists( 'sl9_covid_19_save_post_locations' ) ) {
  function sl9_covid_19_save_post_locations( $post_ID, $post, $update ) {
    // Delete site transients
    if ( is_main_site() ) { sl9_covid_19_delete_site_transients(); }
  }
  add_action( 'save_post_locations', 'sl9_covid_19_save_post_locations', 0, 3 );
}
