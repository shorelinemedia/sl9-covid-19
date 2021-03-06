<?php
/**
* Plugin Name:          Shoreline COVID 19
* Plugin URI:           https://github.com/shorelinemedia/sl9-covid-19
* Description:          Add a banner to a WP Multisite indicating availability of COVID 19 test kits
* Version:              1.0.16
* Author:               Shoreline Media
* Author URI:           https://shoreline.media
* License:              GNU General Public License v2
* License URI:          http://www.gnu.org/licenses/gpl-2.0.html
* Text Domain:          sl9-covid-19
* GitHub Plugin URI:    https://github.com/shorelinemedia/sl9-covid-19
*/

define( 'SL9_COVID_19_PATH', plugin_dir_path( __FILE__ ) );

// Register deactivation hook
register_deactivation_hook( __FILE__, 'sl9_covid_19_deactivation' );

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

    $wp_customize->add_setting("sl9_covid_19_body_hook", array(
      "default" => "sl9_scriptscodes",
      "transport" => "refresh"
    ));

    $wp_customize->add_control( new WP_Customize_Control( $wp_customize, 'sl9_covid_19_body_hook', array(
      'label'         => __( 'Banner Hook' ),
      'description'   => __( 'What hook should we use to insert the banner? Not all templates/themes have the <code>wp_body_open</code> hook available. If you leave this hook blank, the banner will not be inserted. You can use multiple hooks by using commas: <code>sl9_scriptscodes, wp_footer</code>. Default:  <code>sl9_scriptscodes</code>' ),
      'section'       => 'sl9_covid_19',
      'settings'      => 'sl9_covid_19_body_hook',
      'type'          => 'text'
    ) ) );

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

// Get the body hook
if ( !function_exists( 'sl9_covid_19_get_body_hook' ) ) {
  function sl9_covid_19_get_body_hook() {
    return get_theme_mod('sl9_covid_19_body_hook', 'sl9_scriptscodes');
  }
}

// Get ACF custom fields individually since get_fields() breaks in multisite
if ( !function_exists( 'sl9_covid_19_get_custom_fields' ) ) {
  function sl9_covid_19_get_custom_fields( $post_id = '' ) {
    if ( !$post_id ) return false;
    if ( !function_exists( 'get_field' ) ) return false;

    $default_field_names = array(
      'coronavirus_test_kits_available',
      'coronavirus_testing_hours_today',
      'coronavirus_current_weekly_hours',
      'coronavirus_weekly_testing_hours_text',
      'coronavirus_preregistration_required',
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

    if ( false === ( $locations = get_site_option( 'sl9_covid_19_locations', false ) ) ) {

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
      wp_reset_postdata();

      // Update transient for one hour
      update_site_option( 'sl9_covid_19_locations', $locations );

      restore_current_blog();

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

      // Get site url and remove https
      $site_url = str_replace(array('http://','https://'), '', get_site_url());

      // Visit location custom field
      foreach ( $locations as $loc => $val ) {
        $visit_location = str_replace(array('http://','https://'), '', $val['visit_location']);
        // Remove protocol from link

        $array_search = preg_grep( "/" . preg_quote($site_url, '/') . "/i", $visit_location );

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
    wp_register_script( 'popper', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js', array( 'jquery' ), null, false );
    wp_register_script( 'sl9-covid-19', plugins_url( 'assets/js/app.js', __FILE__ ), array( 'popper' ), null, false );
    if ( is_main_site() ) {
      wp_enqueue_script( 'popper' );
      wp_enqueue_script( 'sl9-covid-19' );
    }
  }
  add_action( 'wp_enqueue_scripts', 'sl9_covid_19_banner_assets' );
}

// Add JS variables for our script
if ( !function_exists( 'sl9_covid_19_script_vars' ) ) {
  function sl9_covid_19_script_vars() {
    // Script vars array
    $vars = array();

    $location = sl9_covid_19_get_location();
    if ( !empty( $location ) ) {
      $vars['location'] = $location['post_name'];
    }

    wp_localize_script( 'sl9-covid-19', 'sl9_covid_19', $vars );

  }
  add_action( 'wp_enqueue_scripts', 'sl9_covid_19_script_vars', 20 );
}

// Shortcode to output the banner
if ( !function_exists( 'sl9_covid_19_test_kits_banner_shortcode' ) ) {
  function sl9_covid_19_test_kits_banner_shortcode( $atts = array(), $content = null ) {
       extract(shortcode_atts(array(
          'text' => '',
       ), $atts));

       // Build html
       $html = $testing_hours = $kits_available = $prereg_reqd = '';

       $is_main_site = is_main_site();


       // Enqueue styles
       wp_enqueue_style( 'sl9_covid_19_banner' );
       // Get the location
       $location = sl9_covid_19_get_location();
       $testing_hours = $todays_hours = false;



       if ( !empty( $location ) ) {
         $todays_hours = sl9_covid_19_location_get_todays_hours();
         $kits_available = !empty( $location['coronavirus_test_kits_available'] ) ? $location['coronavirus_test_kits_available'] : $kits_available;
         $testing_hours = !empty( $location['coronavirus_testing_hours_today'] ) ? $location['coronavirus_testing_hours_today'] : $todays_hours;
         $prereg_reqd = !empty( $location['coronavirus_preregistration_required'] ) ? $location['coronavirus_preregistration_required'] : $prereg_reqd;
       } elseif ( $is_main_site ) {
         $kits_available = true;
       } else { return false; }

       $testing_time = false === $todays_hours && false === $testing_hours ? 'today' : ( !empty( $testing_hours ) ? 'today from ' . $testing_hours : '' );

       // Set default text based on customizer checkbox
       $default_text = false === $todays_hours || ( !empty( $testing_time ) && !empty( $testing_hours ) ) ? 'Coronavirus Testing <strong>Available ' . $testing_time . '!</strong> ' : 'Coronavirus Testing Available Today';
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
           $button_text = $kits_available ? ( !empty( $prereg_reqd ) ? 'Pre-Registration <span style="color:red">Required</span>' : 'Learn More and Preregister' ) : 'Learn More';
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

    // Hook our schedule event to a function
    add_action( 'sl9_covid_19_event_delete_transients', 'sl9_covid_19_nightly_transient_clear' );

    // Custom message customizer control
    include_once( SL9_COVID_19_PATH . 'inc/class-wp-customize-message-control.php' );

    // Hook the shortcode output directly into the template based on Customizer settings
    $body_hook = sl9_covid_19_get_body_hook();

    $hooks = array_map('trim', explode(",", $body_hook) );

    foreach ($hooks as $hook) {
      add_action( $hook, 'sl9_covid_19_add_banner_to_body' );
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
    $prereg_reqd = $location['coronavirus_preregistration_required'];
    $location_url = trailingslashit($location['visit_location']['url']);
    $html_class = !empty( $kits_available ) ? 'kits-available' : 'kits-unavailable';
    $weekly_testing_text = !empty( $location['coronavirus_weekly_testing_hours_text'] ) ? $location['coronavirus_weekly_testing_hours_text'] : '';
    $text = !empty( $kits_available ) ? 'Coronavirus Testing <strong>Available!</strong>' : 'Coronavirus Testing <strong>is not available</strong> today, please check our other locations';
    $location_button_text = !empty( $kits_available ) ? ( $prereg_reqd ? 'Pre-Registration <span style="color:red;">Required</span>' : 'Preregister Now' ) : 'Learn More';

    // Build buttons
    $buttons = '<div class="btn-toolbar" role="toolbar" aria-label="Coronavirus Info">';
    $buttons .= '<div class="btn-group btn-group-lg" role="group" aria-label="Important Links">
    <a href="' . $location_url . 'coronavirus-testing/" class="btn btn-primary ' . ( !empty( $weekly_testing_text ) ? ' ' : '' )  . '">' . $location_button_text . '</a>';

    // Hours
    if ( !empty( $weekly_testing_text ) ) {
      $icon_clock = file_get_contents( plugin_dir_path( __FILE__ ) . 'assets/images/icon-clock.svg' );
      $buttons .= '<button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      ' . $icon_clock . ' Testing Hours
      </button>
      <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
      <h5 class="dropdown-menu__title">This Week:</h5>
      ' . $weekly_testing_text . '
      </div>';

    }
    $buttons .= '</div><!-- .btn-group -->';
    $buttons .= '</div><!-- .btn-toolbar -->';
    ?>

    <div class="location-kit-availability <?php echo $html_class; ?>">
      <?php echo $text; ?>
      <?php echo $buttons; ?>
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
      // Enqueue JS for highlighting location columns
      wp_enqueue_script( 'popper' );
      wp_enqueue_script( 'sl9-covid-19' );
      $html .= '<div class="sl9-covid-19-location-schedule__wrap">';
      $html .= '<div class="sl9-covid-19-location-schedule">';
      $html .= '<h2>Coronavirus (COVID-19) Testing Locations</h2>';
      $html .= $location_schedule;
      $html .= '</div>';
      $html .= '</div>';
    }


    return do_shortcode( $html );

  }
  add_shortcode( 'sl9_covid_19_location_schedule', 'sl9_covid_19_location_schedule' );
}


// Delete site transients
if ( !function_exists( 'sl9_covid_19_delete_site_transients' ) ) {
  function sl9_covid_19_delete_site_transients( $all = false ) {
    if ( $all ) { delete_site_option( 'sl9_covid_19_locations' ); }
    delete_site_transient( 'sl9_covid_19_location_schedule' );
  }
}

// Clear transients when Customizer is saved
if ( !function_exists( 'sl9_covid_19_customizer_save_after' ) ) {
  function sl9_covid_19_customizer_save_after( $wp_customize ) {
    // Delete site transients
    if ( is_main_site() ) { sl9_covid_19_delete_site_transients(); }
  }
  add_action( 'customize_save_after', 'sl9_covid_19_customizer_save_after' );
}

// Clear transients when location post types are saved
if ( !function_exists( 'sl9_covid_19_save_post_locations' ) ) {
  function sl9_covid_19_save_post_locations( $post_ID, $post, $update ) {
    // Delete site transients
    if ( is_main_site() ) { sl9_covid_19_delete_site_transients( true ); }
  }
  add_action( 'save_post_locations', 'sl9_covid_19_save_post_locations', 0, 3 );
}


// Add 'integrity' and 'crossorigin' to popper.js and other remote scripts
if ( !function_exists( 'sl9_covid_19_remote_scripts_enqueue' ) ) {
  function sl9_covid_19_remote_scripts_enqueue( $html, $handle = '' ) {
    if ( false !== array_search( $handle, array( 'popper', 'popper.js') ) ) {
      return str_replace( "media='all'", "media='all' integrity='sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49' crossorigin='anonymous'", $html );
    }
    return $html;
  }
  add_filter( 'style_loader_tag', 'sl9_covid_19_remote_scripts_enqueue', 10, 2 );
}

// Get today's hourly testing schedule for a location
if ( !function_exists( 'sl9_covid_19_location_get_todays_hours' ) ) {
  function sl9_covid_19_location_get_todays_hours() {
    $todays_hours = '';
    $location = sl9_covid_19_get_location();
    if ( !$location ) return false;


    // Current timestamp using site's timezone settings
    $current_time = current_time( 'timestamp' );
    $weekday = strtolower( date( 'D', $current_time ) ); // mon, sun, etc

    // Get the hourly testing times
    if ( !empty( $location['coronavirus_current_weekly_hours'] ) ) {

      $times = $location['coronavirus_current_weekly_hours'];
      if ( empty( $times ) || !is_array( $times ) ) return false;

      // Loop through each day/block and try to grab the daily hours or grab value for 'all'
      foreach ( $times as $time ) {
        if ( ( false !== strpos( $time['weekday'], $weekday ) ) || (  $time['weekday'] == 'all' ) ) {
          $hours = $time['hours'][0];
          if ( !empty( $hours ) ) {
            $todays_hours = $hours['start'] . ' - ' . $hours['end'];
          }

        }

      }

    }

    return $todays_hours;
  }
}

// Shortcode to output today's hours
if ( !function_exists( 'sl9_covid_19_location_todays_hours_shortcode' ) ) {
  function sl9_covid_19_location_todays_hours_shortcode( $atts = array(), $content = null ) {
    $location = sl9_covid_19_get_location();
    if ( !$location ) return false;

    $kits_available = $location['coronavirus_test_kits_available'];

    $todays_hours = sl9_covid_19_location_get_todays_hours();
    if ( !$kits_available || empty( $todays_hours ) ) return "No testing today.";

    return $todays_hours;

  }
  add_shortcode( 'covid_19_todays_hours', 'sl9_covid_19_location_todays_hours_shortcode' );
}

// Delete site transients via scheduled events
if ( !function_exists( 'sl9_covid_19_nightly_transient_clear' ) ) {
  function sl9_covid_19_nightly_transient_clear() {
    sl9_covid_19_flush_cache();
  }
}

if ( !function_exists( 'sl9_covid_19_flush_cache' ) ) {
  function sl9_covid_19_flush_cache() {
    if ( function_exists( 'rocket_clean_domain' ) ) {
      // Flush WP-Rocket cache
      rocket_clean_domain();
    } elseif ( function_exists( 'wp_cache_flush' ) ) {
      // Flush WP cache
      wp_cache_flush();
    }
  }
}

// Do stuff on plugin activation
if ( !function_exists( 'sl9_covid_19_activation' ) ) {
  function sl9_covid_19_activation() {
    // Setup cron job
    if ( !wp_next_scheduled ( 'sl9_covid_19_event_delete_transients' ) ) {
        wp_schedule_event( sl9_wpstrtotime('00:00:00'), 'daily', 'sl9_covid_19_event_delete_transients' );
    }
    // Flush cache
    sl9_covid_19_flush_cache();
  }
  add_action( 'init', 'sl9_covid_19_activation' );
}

if ( !function_exists( 'sl9_covid_19_deactivation' ) ) {
  function sl9_covid_19_deactivation() {
    global $blog_id;
    if ( empty( $blog_id ) ) return false;
    // Clear scheduled hook on every site
    $blogs = get_sites();

    foreach ( $blogs as $key => $val ) {
      switch_to_blog( $val->blog_id );

      wp_unschedule_hook( 'sl9_covid_19_event_delete_transients' );
      // Flush cache
      sl9_covid_19_flush_cache();

      restore_current_blog( $blog_id );
    }
  }
  // Register deactivation hook
  register_deactivation_hook( __FILE__, 'sl9_covid_19_deactivation' );

}

// Get a string to time based on site's timezone settings
if ( !function_exists( 'sl9_wpstrtotime' ) ) {
  function sl9_wpstrtotime($str, $format = 'U') {
    // This function behaves a bit like PHP's StrToTime() function, but taking into account the Wordpress site's timezone
    // CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
    // From https://mediarealm.com.au/

    $tz_string = get_option('timezone_string');
    $tz_offset = get_option('gmt_offset', 0);

    if (!empty($tz_string)) {
        // If site timezone option string exists, use it
        $timezone = $tz_string;

    } elseif ($tz_offset == 0) {
        // get UTC offset, if it isn’t set then return UTC
        $timezone = 'UTC';

    } else {
        $timezone = $tz_offset;

        if(substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != $format) {
            $timezone = "+" . $tz_offset;
        }
    }

    $datetime = new DateTime($str, new DateTimeZone($timezone));
    return $datetime->format($format);
  }

}
