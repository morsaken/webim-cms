(function ($) {
  'use strict';

  var cfg = {
    defAnimation: "fadeInUp",    // default css animation
    scrollDuration: 800,         // smoothscroll duration
    statsDuration: 4000
  }, $WIN = $(window);

  $(document).ajaxStart(function () {
    ssBlock(true);
  }).ajaxStop(function () {
    ssBlock(false);
  });

  /* Preloader
   * -------------------------------------------------- */
  var appPreloader = function () {
    $WIN.on('load', function () {
      // will first fade out the loading animation
      $('#loader').fadeOut('slow', function () {
        // will fade out the whole DIV that covers the website.
        $('#preloader').delay(300).fadeOut('slow');
      });
    });
  };

  var ssBlock = function(show) {
    if (show) {
      $.blockUI({
        'message': '<div class="loader-ellipse infinite-scroll-request">' +
        '<span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span>' +
        '</div>',
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
  };

  /* audio controls
   * -------------------------------------------------- */
  var appMediaElementPlayer = function () {
    $('audio').mediaelementplayer({
      features: ['playpause', 'progress', 'tracks', 'volume']
    });
  };

  /* FitVids
  ------------------------------------------------------ */
  var appFitVids = function () {
    $('.fluid-video-wrapper').fitVids();
  };

  /* Alert Boxes
    ------------------------------------------------------- */
  var appAlertBoxes = function () {
    $('.alert-box').on('click', '.close', function () {
      $(this).parent().fadeOut(500);
    });
  };

  /* superfish
   * -------------------------------------------------- */
  var appSuperFish = function () {
    $('ul.sf-menu').superfish({
      animation: {height: 'show'}, // slide-down effect without fade-in
      animationOut: {height: 'hide'}, // slide-up effect without fade-in
      cssArrows: false, // disable css arrows
      delay: 600 // .6 second delay on mouseout
    });
  };

  /* Mobile Menu
 ------------------------------------------------------ */
  var appMobileNav = function () {
    var toggleButton = $('.menu-toggle'),
      nav = $('.main-navigation');

    toggleButton.on('click', function (event) {
      event.preventDefault();

      toggleButton.toggleClass('is-clicked');
      nav.slideToggle();
    });

    if (toggleButton.is(':visible')) nav.addClass('mobile');

    $WIN.resize(function () {
      if (toggleButton.is(':visible')) nav.addClass('mobile');
      else nav.removeClass('mobile');
    });

    $('#main-nav-wrap li a').on("click", function () {
      if (nav.hasClass('mobile')) {
        toggleButton.toggleClass('is-clicked');
        nav.fadeOut();
      }
    });
  };

  /* search
   ------------------------------------------------------ */
  var appSearch = function () {
    var searchWrap = $('.search-wrap');
    var searchField = searchWrap.find('.search-field');
    var closeSearch = $('#close-search');
    var searchTrigger = $('.search-trigger');
    var body = $('body');

    searchTrigger.on('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $this = $(this);

      body.addClass('search-visible');
      window.setTimeout(function () {
        $('.search-wrap').find('.search-field').focus();
      }, 100);
    });

    closeSearch.on('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      var $this = $(this);

      if (body.hasClass('search-visible')) {
        body.removeClass('search-visible');
        window.setTimeout(function () {
          $('.search-wrap').find('.search-field').blur();
        }, 100);
      }
    });

    searchWrap.on('click', function (e) {
      if (!$(e.target).is('.search-field')) {
        closeSearch.trigger('click');
      }
    });

    searchField.on('click', function (e) {
      e.stopPropagation();
    });
  };

  /* Masonry
  ------------------------------------------------------ */
  var appMasonryFolio = function () {
    var $grid = $('.bricks-wrapper');

    $grid.imagesLoaded(function () {
      $grid.masonry({
        itemSelector: '.entry',
        columnWidth: '.grid-sizer',
        percentPosition: true,
        resize: true
      });

      if ($grid.data('infinite')) {
        $grid.infiniteScroll({
          path: $('body').data('root') + '/blog/' + ($grid.data('category') || 'all') + '/{{#}}',
          responseType: 'json',
          outlayer: $grid.data('masonry'),
          status: '.load-status',
          history: false
        });

        $grid.on('load.infiniteScroll', function (event, response) {
          if (response['list'].length) {
            // compile data into HTML
            var itemsHTML = response['list'].map(getItemHTML).join('');

            // convert HTML string into elements
            var $items = $(itemsHTML);

            // append item elements
            $items.imagesLoaded(function () {
              $grid.infiniteScroll('appendItems', $items).masonry('appended', $items);
              appMediaElementPlayer();
            });
          }

          if (!response['next']) {
            $grid.infiniteScroll('option', {
              loadOnScroll: false
            });

            $('.load-status').show().find('.infinite-scroll-last').show();
          }
        });

        // load initial page
        $grid.infiniteScroll('loadNextPage');
      }
    });

    function getItemHTML(article) {
      var template = $('#article-template').html(), head = '';

      if (article.media) {
        if (article.type == 'video') {
          head = '<div class="entry-thumb video-image">' +
            '<a href="' + article.media + '" data-lity><img src="{poster}" alt="{title}"></a>' +
            '</div>';
        } else if (article.type == 'audio') {
          head = '<div class="entry-thumb">' +
            '<div class="audio-wrap">' +
            '<audio src="' + article.media + '" controls="controls" style="width: 100%; height: 42px"></audio>' +
            '</div>' +
            '</div>';
        }
      } else if (article.poster) {
        head = '<div class="entry-thumb">' +
          '<a href="{url}" class="thumb-link"><img src="{poster}" alt="{title}"></a>' +
          '</div>';
      }

        template = template.replace('{head}', head);

      var categories = [];

      article.categories.forEach(function(category) {
        categories.push('<a href="' + category.url + '">' + category.title + '</a>');
      });

      article.categories = categories.join('');

      return renderTemplate(template, article);
    }

    function renderTemplate(src, data) {
      // replace {tags} in source
      return src.replace(/\{([\w\-_\.]+)\}/gi, function(match, key) {
        var html = data;

        key.split('.').forEach(function( part ) {
          html = html[ part ];
        });

        return html;
      });
    }
  };


  /* animate bricks
	* ------------------------------------------------------ */
  var appBricksAnimate = function () {

    var animateEl = $('.animate-this');

    $WIN.on('load', function () {
      setTimeout(function () {
        animateEl.each(function (ctr) {
          var el = $(this);

          setTimeout(function () {
            el.addClass('animated fadeInUp');
          }, ctr * 200);

        });
      }, 200);
    });

    $WIN.on('resize', function () {
      // remove animation classes
      animateEl.removeClass('animate-this animated fadeInUp');
    });

  };


  /* Flex Slider
	* ------------------------------------------------------ */
  var appFlexSlider = function () {

    $WIN.on('load', function () {

      $('#featured-post-slider').flexslider({
        namespace: "flex-",
        controlsContainer: "", // ".flex-content",
        animation: 'fade',
        controlNav: false,
        directionNav: true,
        smoothHeight: false,
        slideshowSpeed: 7000,
        animationSpeed: 600,
        randomize: false,
        touch: true
      });

      $('.post-slider').flexslider({
        namespace: "flex-",
        controlsContainer: "",
        animation: 'fade',
        controlNav: true,
        directionNav: false,
        smoothHeight: false,
        slideshowSpeed: 7000,
        animationSpeed: 600,
        randomize: false,
        touch: true,
        start: function (slider) {
          if (typeof slider.container === 'object') {
            slider.container.on("click", function (e) {
              if (!slider.animating) {
                slider.flexAnimate(slider.getTarget('next'));
              }
            });
          }

          $('.bricks-wrapper').masonry('layout');
        }
      });

    });
  };


  /* Smooth Scrolling
	* ------------------------------------------------------ */
  var appSmoothScroll = function () {

    $('.smoothscroll').on('click', function (e) {
      var target = this.hash,
        $target = $(target);

      e.preventDefault();
      e.stopPropagation();

      if ($target.get(0)) {
        $('html, body').stop().animate({
          'scrollTop': $target.offset().top
        }, cfg.scrollDuration, 'swing').promise().done(function () {
          // check if menu is open
          if ($('body').hasClass('menu-is-open')) {
            $('#header-menu-trigger').trigger('click');
          }
        });
      }
    });

  };


  /* Placeholder Plugin Settings
	* ------------------------------------------------------ */
  var appPlaceholder = function () {
    $('input, textarea, select').placeholder();
  };

  /* Back to Top
	* ------------------------------------------------------ */
  var appBackToTop = function () {

    var pxShow = 400,         // height on which the button will show
      fadeInTime = 400,         // how slow/fast you want the button to show
      fadeOutTime = 400,         // how slow/fast you want the button to hide
      scrollSpeed = 300,         // how slow/fast you want the button to scroll to top. can be a value, 'slow', 'normal' or 'fast'
      goTopButton = $('#go-top');

    // Show or hide the sticky footer button
    $(window).on('load scroll', function () {
      if ($(window).scrollTop() >= pxShow) {
        goTopButton.fadeIn(fadeInTime);
      } else {
        goTopButton.fadeOut(fadeOutTime);
      }
    });
  };

  var appForms = function(form) {
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
        toastr['error'](response.statusText);
        btn.prop('disabled', false);
      });
    });
  };


  /* Map
	* ------------------------------------------------------ */
  var appGoogleMap = function () {

    if (typeof google === 'object' && typeof google.maps === 'object') {

      var latitude = $('#map-wrap').data('lat'),
        longitude = $('#map-wrap').data('lon'),
        map_zoom = $('#map-wrap').data('zoom'),
        main_color = '#d8ac00',
        saturation_value = -30,
        brightness_value = 5,
        marker_url = null,
        winWidth = $(window).width();

      // show controls
      $("#map-zoom-in, #map-zoom-out").show();

      // marker url
      if (winWidth > 480) {
        marker_url = 'img/icon-location@2x.png';
      } else {
        marker_url = 'img/icon-location.png';
      }

      // map style
      var style = [
        {
          // set saturation for the labels on the map
          elementType: "labels",
          stylers: [
            {saturation: saturation_value}
          ]
        },
        {	// poi stands for point of interest - don't show these lables on the map
          featureType: "poi",
          elementType: "labels",
          stylers: [
            {visibility: "off"}
          ]
        },
        {
          // don't show highways lables on the map
          featureType: 'road.highway',
          elementType: 'labels',
          stylers: [
            {visibility: "off"}
          ]
        },
        {
          // don't show local road lables on the map
          featureType: "road.local",
          elementType: "labels.icon",
          stylers: [
            {visibility: "off"}
          ]
        },
        {
          // don't show arterial road lables on the map
          featureType: "road.arterial",
          elementType: "labels.icon",
          stylers: [
            {visibility: "off"}
          ]
        },
        {
          // don't show road lables on the map
          featureType: "road",
          elementType: "geometry.stroke",
          stylers: [
            {visibility: "off"}
          ]
        },
        // style different elements on the map
        {
          featureType: "transit",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "poi",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "poi.government",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "poi.sport_complex",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "poi.attraction",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "poi.business",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "transit",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "transit.station",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "landscape",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]

        },
        {
          featureType: "road",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "road.highway",
          elementType: "geometry.fill",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        },
        {
          featureType: "water",
          elementType: "geometry",
          stylers: [
            {hue: main_color},
            {visibility: "on"},
            {lightness: brightness_value},
            {saturation: saturation_value}
          ]
        }
      ];

      // map options
      var map_options = {
        center: new google.maps.LatLng(latitude, longitude),
        zoom: map_zoom,
        panControl: false,
        zoomControl: false,
        mapTypeControl: false,
        streetViewControl: false,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        scrollwheel: false,
        styles: style
      };

      // inizialize the map
      var map = new google.maps.Map(document.getElementById('map-container'), map_options);

      // add a custom marker to the map
      var marker = new google.maps.Marker({
        position: new google.maps.LatLng(latitude, longitude),
        map: map,
        visible: true,
        icon: marker_url
      });

      // add custom buttons for the zoom-in/zoom-out on the map
      var CustomZoomControl = function(controlDiv, map) {
        // grap the zoom elements from the DOM and insert them in the map
        var controlUIzoomIn = document.getElementById('map-zoom-in'),
          controlUIzoomOut = document.getElementById('map-zoom-out');

        controlDiv.appendChild(controlUIzoomIn);
        controlDiv.appendChild(controlUIzoomOut);

        // Setup the click event listeners and zoom-in or out according to the clicked element
        google.maps.event.addDomListener(controlUIzoomIn, 'click', function () {
          map.setZoom(map.getZoom() + 1)
        });
        google.maps.event.addDomListener(controlUIzoomOut, 'click', function () {
          map.setZoom(map.getZoom() - 1)
        });
      };

      var zoomControlDiv = document.createElement('div');
      var zoomControl = new CustomZoomControl(zoomControlDiv, map);

      // insert the zoom div on the top right of the map
      map.controls[google.maps.ControlPosition.TOP_RIGHT].push(zoomControlDiv);
    }
  };


  /* Initialize
	* ------------------------------------------------------ */
  (function ssInit() {
    appPreloader();
    appMediaElementPlayer();
    appFitVids();
    appAlertBoxes();
    appSuperFish();
    appMobileNav();
    appSearch();
    appMasonryFolio();
    appBricksAnimate();
    appFlexSlider();
    appSmoothScroll();
    appPlaceholder();
    appBackToTop();
    appGoogleMap();
    appForms('#contact-form');
    appForms('#newsletter-form');
  })();

})(jQuery);