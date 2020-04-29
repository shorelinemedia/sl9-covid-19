(function($) {

  var resizeTimer;


  $.fn.sl9CovidBanner = function( small ) {
    var el = $(this),
        className = 'btn-group-vertical';

    if ( !window.matchMedia('screen and (min-width: 768px)').matches ) {
      el.addClass(className);
    } else {
      el.removeClass(className);
    }
  }

  $.fn.sl9LocationTableHighlight = function() {
    if ( !window.sl9_covid_19 ) { return false; }

    var location = window.sl9_covid_19.location;
    if ( location ) {
      // Highlight the appropriate table cells 
      $(this).find('.' + location ).addClass('table-cell__highlighted');
    }

    return this;
  }

  $(document).on('ready', function() {

    $('.location-kit-availability .btn-group').sl9CovidBanner();

    $('.sl9-covid-19-location-schedule table').sl9LocationTableHighlight();

  });

  $(window).on('resize', function(e) {

    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {

      $('.location-kit-availability .btn-group').sl9CovidBanner();

    }, 250);

  });

})(jQuery);
