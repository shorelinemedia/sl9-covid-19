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

  $(document).on('ready', function() {

    $('.location-kit-availability .btn-group').sl9CovidBanner();

  });

  $(window).on('resize', function(e) {

    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {

      $('.location-kit-availability .btn-group').sl9CovidBanner();

    }, 250);

  });

})(jQuery);
