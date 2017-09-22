(function ($) {

  var root = $('base').attr('href');

  //Preloader
  function handlePreloader() {
    if($('.preloader').length){
      $('.preloader').delay(500).fadeOut(500);
    }
  }

  //Loading
  function loader(show) {
    if (show) {
      $.blockUI({
        'message': '<div class="loader"><div class="spinner-bar"><div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div></div></div>',
        'baseZ': 10000,
        'css': {
          border: '0',
          padding: '0',
          backgroundColor: 'none'
        },
        'overlayCSS': {
          backgroundColor: '#555',
          opacity: 0.05,
          cursor: 'wait'
        }
      });
    } else {
      $.unblockUI();
    }
  }

  //Header Style + Scroll to Top
  function headerStyle() {
    if($('.main-header').length){
      var windowpos = $(window).scrollTop();
      if (windowpos >= 50) {
        $('.main-header').addClass('fixed-header');
        $('.scroll-to-top').fadeIn(300);
      } else {
        $('.main-header').removeClass('fixed-header');
        $('.scroll-to-top').fadeOut(300);
      }
    }
  }

  function banner(target) {
    if ($(target).revolution !== undefined){
      $(target).show().revolution({
        //dottedOverlay:"yes",
        delay:10000,
        startwidth:1200,
        startheight:850,
        hideThumbs:600,

        thumbWidth:80,
        thumbHeight:50,
        thumbAmount:5,

        navigationType:"bullet",
        navigationArrows:"0",
        navigationStyle:"preview4",

        touchenabled:"on",
        onHoverStop:"off",

        swipe_velocity: 0.7,
        swipe_min_touches: 1,
        swipe_max_touches: 1,
        drag_block_vertical: false,

        parallax:"mouse",
        parallaxBgFreeze:"on",
        parallaxLevels:[7,4,3,2,5,4,3,2,1,0],

        keyboardNavigation:"off",

        navigationHAlign:"center",
        navigationVAlign:"bottom",
        navigationHOffset:0,
        navigationVOffset:20,

        soloArrowLeftHalign:"left",
        soloArrowLeftValign:"center",
        soloArrowLeftHOffset:20,
        soloArrowLeftVOffset:0,

        soloArrowRightHalign:"right",
        soloArrowRightValign:"center",
        soloArrowRightHOffset:20,
        soloArrowRightVOffset:0,

        shadow:0,
        fullWidth:"on",
        fullScreen:"off",

        spinner:"spinner4",

        stopLoop:"off",
        stopAfterLoops:-1,
        stopAtSlide:-1,

        shuffle:"off",

        autoHeight:"off",
        forceFullWidth:"on",

        hideThumbsOnMobile:"on",
        hideNavDelayOnMobile:1500,
        hideBulletsOnMobile:"on",
        hideArrowsOnMobile:"on",
        hideThumbsUnderResolution:0,

        hideSliderAtLimit:0,
        hideCaptionAtLimit:0,
        hideAllCaptionAtLilmit:0,
        startWithSlide:0
      });
    }
  }

  function postForm(form) {
    $(form).find('input').on('change invalid', function() {
      var field = $(this).get(0);

      field.setCustomValidity('');

      if (!field.validity.valid) {
        field.setCustomValidity($(this).data('message') || 'Lütfen işaretli yerleri doldurunuz');
      }
    });

    $(form).submit(function(e) {
      e.preventDefault();

      var btn = $('[type="submit"]', this);
      btn.prop('disabled', true);

      $.ajax({
        'type': 'POST',
        'url': $(this).attr('action'),
        'data': $(this).serialize()
      }).done(function(result) {
        if (result.success) {
          $('input[type="text"], input[type="email"], select, textarea', form).val('');
        }

        toastr[(result.success ? 'success' : 'error')](result.text);

        btn.prop('disabled', false);
      }).fail(function(response, status, xhr) {
        toastr['error'](response);
        btn.prop('disabled', false);
      });
    });
  }

  // Scroll to top
  if ($('.scroll-to-top').length){
    $('.scroll-to-top').on('click', function() {
      // animate
      $('html, body').animate({
        scrollTop: $('html, body').offset().top
      }, 1000);

    });
  }

  $(document).ajaxStart(function () {
    loader(true);
  }).ajaxStop(function () {
    loader(false);
  });

  headerStyle();
  banner('#banner');
  postForm('#newsletter-form');
  postForm('#contact-form');

  //Services Slider
  if ($('.services-slider').length){
    $('.services-slider').bxSlider({
      adaptiveHeight: true,
      auto: true,
      controls: true,
      pause: 5000,
      speed: 1000,
      nextText: '<span class="control-icon icon-arrow-right"></span>',
      prevText: '<span class="control-icon icon-arrow-left"></span>',
      pagerCustom: '#services-pager'
    });
  }

  if ($('#map').length){
    var map;
    map = new GMaps({
      el: '#map',
      zoom: parseFloat($('#map').attr('data-zoom')),
      lat: $('#map').attr('data-lat'),
      lng: $('#map').attr('data-lon'),
      scrollwheel:false
    });

    //Add map Marker
    map.addMarker({
      lat: $('#map').attr('data-marker-lat'),
      lng: $('#map').attr('data-marker-lon'),
      infoWindow: {
        content: '<p class="info-outer"><strong>' + $('#map').attr('data-title') + '</strong><br>' + $('#map').attr('data-marker-content') + '</p>'
      }
    });
  }

  $(window).on('scroll', function() {
    headerStyle();
  });

  $(window).on('load', function() {
    handlePreloader();
  });

})(jQuery);
