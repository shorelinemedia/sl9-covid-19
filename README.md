# COVID 19 Banner

This plugin adds a Customizer section with a checkbox that indicates the test kit status for a website on a multisite network. The main site's banner about Coronavirus will include a message to check the location websites for availability

### Shortcode
To use the banner outside the template use shortcode `[sl9_covid_19_banner]`

### Filters
To tell the plugin which location custom fields to use, pass the field names in an array:

``
add_filter( 'sl9_covid_19_acf_location_fields', function() {
  return array(
    'visit_location',
    'zipcode'
  );
});
``
