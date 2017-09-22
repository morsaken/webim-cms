(function ($) {

  'use strict';

  moment.locale($('html').attr('lang'));

  /*==============================================================*/
  // # sticky-nav
  /*==============================================================*/
  if ($(window).width() > 1000) {
    $(window).scroll(function () {
      var sT = $(this).scrollTop();
      if (sT >= 120) {
        $('.homepage .navbar, .homepage-two.fixed-nav .navbar').addClass('sticky-nav');
        $('.homepage #menubar, .homepage-two.fixed-nav #navigation').removeClass('container');
        $('.homepage #menubar, .homepage-two.fixed-nav #navigation').addClass('container-fluid');
      } else {
        $('.homepage .navbar, .homepage-two.fixed-nav .navbar').removeClass('sticky-nav');
        $('.homepage #menubar, .homepage-two.fixed-nav #navigation').removeClass('container-fluid');
        $('.homepage #menubar').addClass('container');
      }
    });
  } else {
    $('.homepage .navbar, .homepage-two.fixed-nav .navbar').removeClass('sticky-nav');
    $('.homepage #menubar, .homepage-two.fixed-nav #navigation').removeClass('container-fluid');
  }

  /*==============================================================*/
  // # Date Time
  /*==============================================================*/

  var datetime = null,
    date = null;
  var update = function () {
    date = moment(new Date());
    datetime.html(date.format('LL, dddd'));
  };
  datetime = $('#date-time');
  update();
  setInterval(update, 1000);

  /*==============================================================*/
  // # language Select
  /*==============================================================*/
  $('.language-dropdown').on('click', '.language-change a', function (ev) {
    if ("#" === $(this).attr('href')) {
      ev.preventDefault();
      var parent = $(this).parents('.language-dropdown');
      parent.find('.change-text').html($(this).html());
    }
  });

  /*==============================================================*/
  // Search Slide
  /*==============================================================*/

  $('.search-icon').on('click', function () {
    $('.search-block').toggleClass('expanded');
  });

  /*==============================================================*/
  // Breaking News
  /*==============================================================*/
  $('.breaking-news-scroll').easyTicker({
    direction: 'up',
    easing: 'swing',
    speed: 'slow',
    interval: 3000,
    height: 'auto',
    visible: 1,
    mousePause: 1,
    controls: {
      up: '',
      down: '',
      toggle: '',
      playText: 'Play',
      stopText: 'Stop'
    }
  });

  /*==============================================================*/
  // sticky
  /*==============================================================*/
  $("#sticky").stick_in_parent();

  /*==============================================================*/
  // Owl Carousel
  /*==============================================================*/
  $("#home-slider").owlCarousel({
    pagination: true,
    autoPlay: true,
    singleItem: true,
    stopOnHover: true
  });

  $("#latest-news").owlCarousel({
    items: 4,
    pagination: true,
    autoPlay: true,
    stopOnHover: true
  });

  $(".twitter-feeds").owlCarousel({
    items: 1,
    singleItem: true,
    pagination: false,
    autoPlay: true,
    stopOnHover: true
  });

  $("#main-slider").owlCarousel({
    items: 3,
    pagination: false,
    navigation: false,
    autoPlay: true,
    stopOnHover: true

  });


  /*==============================================================*/
  // Magnific Popup
  /*==============================================================*/

  $('.image-link').magnificPopup({
    gallery: {
      enabled: true
    },
    type: 'image'
  });
  $('.feature-image .image-link').magnificPopup({
    gallery: {
      enabled: false
    },
    type: 'image'
  });
  $('.image-popup').magnificPopup({
    type: 'image'
  });
  $('.video-link').magnificPopup({type: 'iframe'});

  /*==============================================================*/
// Weather
  /*==============================================================*/

  $.simpleWeather({
    location: 'istanbul, tr',
    woeid: '',
    unit: 'c',
    success: function (weather) {
      var html = '<span>' + weather.city + ' </span><img src="' + weather.thumbnail + '"><span> ' + weather.temp + '&deg;' + weather.units.temp + '</span>';
      $("#weather").html(html);
    },
    error: function (error) {
      $("#weather").html('<p>' + error + '</p>');
    }
  });

  $.simpleWeather({
    location: 'istanbul, tr',
    woeid: '',
    unit: 'c',
    success: function (weather) {
      var html = '';

      for (var i = 4; i < weather.forecast.length; i++) {
        html = '<img class="weather-image" src="' + weather.image + '">' + '<span class="weather-type">' + weather.currently + '</span><span class="weather-temp"> ' + weather.temp + '&deg;' + weather.units.temp + '</span><span class="weather-date">' + weather.forecast[i].date + '</span><span class="weather-region">' + weather.city + ', ' + weather.country + '</span>';
      }

      html += '<span class="weather-humidity">' + weather.humidity + '%</span> ';
      html += '<span class="weather-wind">' + weather.wind.speed + ' MPH</span>';

      $("#weather-widget").html(html);
    },
    error: function (error) {
      $("#weather-widget").html('<p>' + error + '</p>');
    }
  });

})(jQuery);
