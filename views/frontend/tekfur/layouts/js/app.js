(function($) {
  $(document).ajaxStart(function () {
    $.blockUI({
      'css': {
        backgroundColor: '#f2f2f2',
        color: '#000000',
        border: 'none',
        padding: '10px',
        fontSize: '20px'
      },
      'baseZ': 10000,
      'message': '<i class="fa fa-spinner fa-spin"></i>'
    });
  }).ajaxStop(function () {
    $.unblockUI();
  });

  $('aside.nav a.toggle').click(function() {
    var target = $('aside.nav > .bar');

    if (target.hasClass('open')) {
      target.removeClass('open');
    } else {
      target.addClass('open');
    }
  });

  $('[data-background-image]').each(function() {
    var img = $(this).data('background-image');

    if (img) {
      $(this).removeAttr('data-background-image').css('background-image', 'url(' + img + ')');
    }
  });

  $('header').parent().clone(true).appendTo('#header');

  var headerOffset = $('header').height() * 1.5;

  $(window).on('load scroll', function () {
    if ($(window).scrollTop() >= headerOffset) {
      if (!$('body').hasClass('fixed')) $('body').addClass('fixed');
    } else {
      $('body').removeClass('fixed');
    }
  });

  if ($().bxSlider) {
    $('.main-slider > ul').bxSlider({
      'mode': 'fade',
      'auto': true,
      'controls': false,
      'pause': 10000
    });

    $('#event-poster > ul').bxSlider({
      'auto': true,
      'autoHover': true,
      'controls': true,
      'pause': 5000,
      'speed': 1000,
      'pagerCustom': '#event-info'
    });

    if ($('#product-poster > ul > li').length > 1) {
      $('#product-poster > ul').bxSlider({
        'auto': true,
        'controls': true,
        'pager': false
      });
    }
  }

  if ($().flexslider) {
    $('#timeline-times').flexslider({
      animation: 'slide',
      controlNav: false,
      animationLoop: false,
      slideshow: false,
      itemWidth: 210,
      itemMargin: 10,
      asNavFor: '#timeline-info'
    });

    $('#timeline-info').flexslider({
      animation: 'slide',
      controlNav: false,
      animationLoop: false,
      slideshow: false,
      sync: '#timeline-times'
    });
  }

  if ($().owlCarousel) {
    $('.main-carousel .owl-carousel').owlCarousel({
      autoplay: true,
      loop: true,
      margin: 0,
      nav: true,
      items: 1,
      navText: ['&lt;', '&gt;']
    });

    $('.banner .owl-carousel').owlCarousel({
      autoplay: true,
      loop: true,
      margin: 0,
      nav: true,
      items: 1,
      navText: ['&lt;', '&gt;']
    });

    $('.slider .owl-carousel').owlCarousel({
      autoplay: true,
      loop: false,
      margin: 0,
      nav: true,
      responsive: {
        0: {
          items: 1
        },
        768: {
          items: 2
        }
      },
      navText: ['&lt;', '&gt;']
    });
  }

  if ($().eventCalendar) {
    $('#calendar').eventCalendar();
  }

  if ($().isotope) {
    $('.gallery').imagesLoaded(function() {
      $('.gallery').isotope({
        itemSelector: '.media'
      });
    });

    $('.products').imagesLoaded(function() {
      $('.products').isotope({
        itemSelector: '.product'
      });
    });
  }

  if ($().iLightBox) {
    $('.gallery .media').iLightBox();
  }

  function categorySizes() {
    var cats = $('ul.categories');

    if (cats.length) {
      var width = cats.parent().width() - cats.find('.all').width();
      var total = cats.find('li:not(.all)').length;

      cats.find('li:not(.all)').css('width', (width - total) / total);
    }
  }

  categorySizes();

  $(window).on('resize', categorySizes);

  if (typeof google != 'undefined') {
    var markerIcon = {
      path: 'M19.9,0c-0.2,0-1.6,0-1.8,0C8.8,0.6,1.4,8.2,1.4,17.8c0,1.4,0.2,3.1,0.5,4.2c-0.1-0.1,0.5,1.9,0.8,2.6c0.4,1,0.7,2.1,1.2,3 c2,3.6,6.2,9.7,14.6,18.5c0.2,0.2,0.4,0.5,0.6,0.7c0,0,0,0,0,0c0,0,0,0,0,0c0.2-0.2,0.4-0.5,0.6-0.7c8.4-8.7,12.5-14.8,14.6-18.5 c0.5-0.9,0.9-2,1.3-3c0.3-0.7,0.9-2.6,0.8-2.5c0.3-1.1,0.5-2.7,0.5-4.1C36.7,8.4,29.3,0.6,19.9,0z M2.2,22.9 C2.2,22.9,2.2,22.9,2.2,22.9C2.2,22.9,2.2,22.9,2.2,22.9C2.2,22.9,3,25.2,2.2,22.9z M19.1,26.8c-5.2,0-9.4-4.2-9.4-9.4 s4.2-9.4,9.4-9.4c5.2,0,9.4,4.2,9.4,9.4S24.3,26.8,19.1,26.8z M36,22.9C35.2,25.2,36,22.9,36,22.9C36,22.9,36,22.9,36,22.9 C36,22.9,36,22.9,36,22.9z M13.8,17.3a5.3,5.3 0 1,0 10.6,0a5.3,5.3 0 1,0 -10.6,0',
      strokeOpacity: 0,
      strokeWeight: 1,
      fillColor: '#a26562',
      fillOpacity: 1,
      rotation: 0,
      scale: 1,
      anchor: new google.maps.Point(19, 50)
    };

    var mapLatLon = {
      lng: $('#map').data('lon'),
      lat: $('#map').data('lat')
    };

    var map = new google.maps.Map(document.getElementById('map'), {
      zoom: $('#map').data('zoom'),
      center: mapLatLon,
      scrollwheel: false,
      zoomControl: false,
      mapTypeControl: false,
      scaleControl: false,
      panControl: false,
      navigationControl: false,
      streetViewControl: false,
      styles: [{
        'featureType': 'poi',
        'elementType': 'labels.text.fill',
        'stylers': [{'color': '#747474'}, {'lightness': '23'}]
      }, {
        'featureType': 'poi.attraction',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#f38eb0'}]
      }, {
        'featureType': 'poi.government',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#ced7db'}]
      }, {
        'featureType': 'poi.medical',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#ffa5a8'}]
      }, {
        'featureType': 'poi.park',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#c7e5c8'}]
      }, {
        'featureType': 'poi.place_of_worship',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#d6cbc7'}]
      }, {
        'featureType': 'poi.school',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#c4c9e8'}]
      }, {
        'featureType': 'poi.sports_complex',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#b1eaf1'}]
      }, {'featureType': 'road', 'elementType': 'geometry', 'stylers': [{'lightness': '100'}]}, {
        'featureType': 'road',
        'elementType': 'labels',
        'stylers': [{'visibility': 'off'}, {'lightness': '100'}]
      }, {
        'featureType': 'road.highway',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#ffd4a5'}]
      }, {
        'featureType': 'road.arterial',
        'elementType': 'geometry.fill',
        'stylers': [{'color': '#ffe9d2'}]
      }, {
        'featureType': 'road.local',
        'elementType': 'all',
        'stylers': [{'visibility': 'simplified'}]
      }, {
        'featureType': 'road.local',
        'elementType': 'geometry.fill',
        'stylers': [{'weight': '3.00'}]
      }, {
        'featureType': 'road.local',
        'elementType': 'geometry.stroke',
        'stylers': [{'weight': '0.30'}]
      }, {
        'featureType': 'road.local',
        'elementType': 'labels.text',
        'stylers': [{'visibility': 'on'}]
      }, {
        'featureType': 'road.local',
        'elementType': 'labels.text.fill',
        'stylers': [{'color': '#747474'}, {'lightness': '36'}]
      }, {
        'featureType': 'road.local',
        'elementType': 'labels.text.stroke',
        'stylers': [{'color': '#e9e5dc'}, {'lightness': '30'}]
      }, {
        'featureType': 'transit.line',
        'elementType': 'geometry',
        'stylers': [{'visibility': 'on'}, {'lightness': '100'}]
      }, {'featureType': 'water', 'elementType': 'all', 'stylers': [{'color': '#d2e7f7'}]}]
    });

    if ($('#map').data('marker-lon')) {
      var marker = new google.maps.Marker({
        position: {
          lng: $('#map').data('marker-lon'),
          lat: $('#map').data('marker-lat')
        }, map: map, icon: markerIcon
      });
    }

    var controlDiv = document.createElement('div');
    var zoomInButton = document.createElement('a');
    var zoomOutButton = document.createElement('a');
    controlDiv.appendChild(zoomInButton);
    controlDiv.appendChild(zoomOutButton);

    controlDiv.className = 'custom-zoom-buttons';
    zoomInButton.setAttribute('href', 'javascript:;');
    zoomInButton.className = 'custom-zoom-in';
    zoomOutButton.setAttribute('href', 'javascript:;');
    zoomOutButton.className = 'custom-zoom-out';

    map.controls[google.maps.ControlPosition.RIGHT_CENTER].push(controlDiv);

    google.maps.event.addDomListener(zoomInButton, 'click', function () {
      map.setZoom(map.getZoom() + 1);
    });

    google.maps.event.addDomListener(zoomOutButton, 'click', function () {
      map.setZoom(map.getZoom() - 1);
    });

    $('a.street-view').click(function (e) {
      e.preventDefault();

      var state = $(this).data('state') == 'street' ? 'vector' : 'street';

      map.getStreetView().setOptions({
        visible: (state == 'street'),
        position: mapLatLon
      });

      $(this).data('state', state).text($(this).data(state + '-text'));
    });
  }

})(jQuery);