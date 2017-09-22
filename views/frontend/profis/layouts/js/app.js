(function($) {
  'use strict';

  var App = function () {
    return {
      'history': [],
      'root': $('base').attr('href'),
      'init': function () {
        var $title = $('title'),
          mainTitle = $title.attr('data-default'),
          separator = $title.attr('data-separator'),
          title = $title.text().replace(' ' + separator + ' ' + mainTitle, '');

        App.history.push({
          'url': window.location.href,
          'title': title.length ? title : mainTitle
        });

        $(document).ajaxStart(function () {
          App.loader(true);
        }).ajaxStop(function () {
          App.loader(false);
        });

        $(window).on('load scroll', function() {
          if ($(window).scrollTop() > 80) {
            $('header').addClass('scrolled');
          } else {
            $('header').removeClass('scrolled');
          }
        });

        $(document).ready(function () {
          var loader = $('.loader-wrapper').find('.loader');

          $(document).imagesLoaded(function () {
            loader.delay(500).fadeOut(function() {
              $(this).parent().fadeOut(500);
            });
          });
        });

        App.menuToggler();
        //App.scroller();
        App.fitHeight('.blocks');
        App.popState();
        App.infoWindow();
        App.search();
        App.forms('#newsletter-form');
        App.plugins();

        $(window).on('resize', function() {
          App.divScroller();
        });
      },
      'plugins': function() {
        $('[data-background-image]', '#content').each(function() {
          var img = $(this).data('background-image');

          if (img) {
            $(this).removeAttr('data-background-image').css('background-image', 'url(' + img + ')');
          }
        });

        $('[data-toggle="tooltip"]').tooltip();

        if (!$('.top.fixed').length) {
          $('.top').clone(true).addClass('fixed').appendTo('#content');
        }

        $('.breadcrumb .back > a').click(function(e) {
          e.preventDefault();

          if (App.history.length > 1) {
            App.setPage(App.history[App.history.length - 2], true);
          }
        });

        if (App.history.length > 1) {
          $('.breadcrumb .back').removeClass('hide');
        }

        $('img.lazy').removeClass('img-responsive').lazyload({
          'placeholder': '',
          'load': function() {
            $(this).addClass('img-responsive');
          }
        });

        App.divScroller();

        if ($('#slider').length) {
          App.loadFiles([
            App.root + 'plugins/rs-plugin/css/settings.css',
            App.root + 'plugins/rs-plugin/css/layers.css',
            App.root + 'plugins/rs-plugin/css/navigation.css',
            App.root + 'plugins/rs-plugin/js/jquery.themepunch.tools.min.js',
            App.root + 'plugins/rs-plugin/js/jquery.themepunch.revolution.min.js'
          ], function() {
            App.revSlider('#slider');
          });
        }

        if ($().owlCarousel) {
          $('.owl-carousel', '#content').owlCarousel({
            autoplay: true,
            loop: false,
            margin: 10,
            nav: true,
            responsiveClass: true,
            responsive: {
              0: {
                items: 1
              },
              650: {
                items: 2
              },
              900: {
                items: 2
              },
              1200: {
                items: 3
              },
              1550: {
                items: 4
              },
              1850: {
                items: 5
              }
            },
            navText: ['&lt;', '&gt;']
          });
        }

        if ($().iLightBox) {
          $('.poster', '#content').iLightBox().destroy();
          $('.gallery .media:not(.pop)', '#content').iLightBox().destroy();
          $('.poster', '#content').each(function() {
            $(this).iLightBox();
          });
          $('.gallery .media:not(.pop)', '#content').iLightBox();
        }

        if ($().isotope) {
          $('#content').imagesLoaded(function() {
            var $grid = $('.grid', '#content').isotope({
              itemSelector: '.grid-item',
              layout: 'masonry',
              percentPosition: true
            });

            $('.filters select').on('change', function () {
              var filterValues = [], val;

              $('.filters select').each(function () {
                val = $(this).val();

                if (val !== '*') {
                  val = '.' + val;

                  if (filterValues.indexOf(val) < 0) {
                    filterValues.push(val);
                  }
                }
              });

              $grid.isotope({filter: filterValues.join(', ')});
            });
          });
        }

        if ($('#map').length && (typeof google != 'undefined')) {
          var markerIcon = {
            path: 'M19.9,0c-0.2,0-1.6,0-1.8,0C8.8,0.6,1.4,8.2,1.4,17.8c0,1.4,0.2,3.1,0.5,4.2c-0.1-0.1,0.5,1.9,0.8,2.6c0.4,1,0.7,2.1,1.2,3 c2,3.6,6.2,9.7,14.6,18.5c0.2,0.2,0.4,0.5,0.6,0.7c0,0,0,0,0,0c0,0,0,0,0,0c0.2-0.2,0.4-0.5,0.6-0.7c8.4-8.7,12.5-14.8,14.6-18.5 c0.5-0.9,0.9-2,1.3-3c0.3-0.7,0.9-2.6,0.8-2.5c0.3-1.1,0.5-2.7,0.5-4.1C36.7,8.4,29.3,0.6,19.9,0z M2.2,22.9 C2.2,22.9,2.2,22.9,2.2,22.9C2.2,22.9,2.2,22.9,2.2,22.9C2.2,22.9,3,25.2,2.2,22.9z M19.1,26.8c-5.2,0-9.4-4.2-9.4-9.4 s4.2-9.4,9.4-9.4c5.2,0,9.4,4.2,9.4,9.4S24.3,26.8,19.1,26.8z M36,22.9C35.2,25.2,36,22.9,36,22.9C36,22.9,36,22.9,36,22.9 C36,22.9,36,22.9,36,22.9z M13.8,17.3a5.3,5.3 0 1,0 10.6,0a5.3,5.3 0 1,0 -10.6,0',
            strokeOpacity: 0,
            strokeWeight: 1,
            fillColor: '#cc0000',
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

        App.forms('#contact-form');
      },
      'menuToggler': function() {
        var menu = $('.menu');
        var target = $('.toggle');

        target.find('> a').click(function(e) {
          e.preventDefault();

          if (target.hasClass('open')) {
            target.removeClass('open');
          } else {
            target.addClass('open');
          }
        });

        menu.find('nav a').click(function() {
          if (target.is(':visible')) {
            menu.removeClass('open');
          }
        });
      },
      'popState': function(context) {
        if (window.history.pushState) {
          var host = window.location.origin;

          $('a[href^="' + host + '"]' +
            ':not([href^="#"])' +
            ':not([href^="javascript:"])' +
            ':not(.no-ajax)' +
            ':not([target])', context).click(function (e) {
            e.preventDefault();

            var url = $(this).attr('href');
            var path = host + window.location.pathname;
            var title = $(this).attr('data-title');

            if (!title) {
              title = $(this).attr('title');

              if (!title) {
                title = $(this).text();
              }
            }

            if (url.length && (url !== path)) {
              App.setPage({
                'url': url,
                'title': title.trim()
              });
            }
          });

          window.onpopstate = function (e) {
            var url = e.state ? e.state.url : null;

            if (url) {
              App.setPage({
                'url': url
              });
            }
          };
        }
      },
      'setPage': function(page, fromHistory) {
        App.loader(true);

        var root = $('#index').attr('href');
        var path = page.url.replace(root, '');

        $('nav').find('li').removeClass('active').closest('nav').find('a').each(function() {
          var uri = $(this).attr('href').replace(root, '');

          if ((uri === path) || (uri.length && path.indexOf(uri) === 0)) {
            $(this).closest('li').addClass('active');
          }
        });

        var $title = $('title'),
          title = $title.attr('data-default'),
          separator = $title.attr('data-separator');

        if (page.title && page.title.length && (page.title !== title) && (page.url.replace(root, '') !== '')) {
          title = page.title + ' ' + separator + ' ' + title;
        }

        $title.text(title);

        if (window.history.pushState) {
          window.history.pushState({url: page.url}, page.title, page.url);
        }

        if (fromHistory) {
          App.history.pop();
        } else {
          App.history.push(page);
        }

        $('#content').load(page.url, function(response, status, xhr) {
          $('body').scrollTop(0);

          if (status === 'error') {
            $('#content').html(response);
          }

          App.popState('#content');
          App.loader(false);

          window.setTimeout(function() {
            App.plugins();
          }, 500);
        });
      },
      'loader': function(show) {
        if (show) {
          $.blockUI({
            'message': '<div class="loader-wrapper transparent">' +
            '<div class="loader">' +
            '<div class="flipper"><div class="front"></div><div class="back"></div></div>' +
            '</div>' +
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
      },
      'scroller': function () {
        $('body').niceScroll({
          cursorcolor: '#cc0000',
          cursorborder: 'none'
        });
      },
      'divScroller': function() {
        $('.scroller', '#content').each(function() {
          $(this).css('height', (
            $(window).height()
            - ($(this).parent().position().top + 60)
            - $(this).parent().find('form').height() - 20)
          );
        }).niceScroll({
          cursorcolor: '#cc0000',
          cursorborder: 'none'
        });
      },
      'fitHeight': function(target) {
        var h = 0;

        $(target).each(function() {
          $(this).find('[class^="col-"]:visible').css('min-height', 0).each(function() {
            if ($(this).outerHeight() > h) {
              h = $(this).outerHeight();
            }
          }).each(function() {
            $(this).css('min-height', h + 1);
          });
        });
      },
      'revSlider': function(target, jsPath) {
        if ($(target).revolution === undefined){
          revslider_showDoubleJqueryError(target);
        } else {
          $(target).show().revolution({
            sliderType: 'carousel',
            jsFileLocation: (jsPath || App.root + 'plugins/rs-plugin/js/'),
            sliderLayout: 'fullwidth',
            dottedOverlay: 'none',
            delay: 9000,
            navigation: {
              keyboardNavigation: 'on',
              keyboard_direction: 'horizontal',
              mouseScrollNavigation: 'off',
              mouseScrollReverse: 'default',
              onHoverStop: 'off',
              touch: {
                touchenabled: 'on',
                touchOnDesktop: 'off',
                swipe_threshold: 75,
                swipe_min_touches: 1,
                swipe_direction: 'horizontal',
                drag_block_vertical: false
              },
              arrows: {
                style: 'erinyen',
                enable: true,
                hide_onmobile: true,
                hide_under: 600,
                hide_onleave: true,
                hide_delay: 200,
                hide_delay_mobile: 1200,
                tmp: '<div class="tp-title-wrap"><div class="tp-arr-imgholder"></div><div class="tp-arr-img-over"></div><span class="tp-arr-titleholder">{{title}}</span></div>',
                left: {
                  h_align: 'left',
                  v_align: 'center',
                  h_offset: 30,
                  v_offset: 0
                },
                right: {
                  h_align: 'right',
                  v_align: 'center',
                  h_offset: 30,
                  v_offset: 0
                }
              },
              thumbnails: {
                style: 'gyges',
                enable: true,
                width: 60,
                height: 60,
                min_width: 60,
                wrapper_padding: 0,
                wrapper_color: 'transparent',
                tmp: '<span class="tp-thumb-img-wrap"><span class="tp-thumb-image"></span></span>',
                visibleAmount: 5,
                hide_onmobile: true,
                hide_under: 800,
                hide_onleave: false,
                direction: 'horizontal',
                span: false,
                position: 'inner',
                space: 5,
                h_align: 'center',
                v_align: 'bottom',
                h_offset: 0,
                v_offset: 20
              }
            },
            carousel: {
              horizontal_align: 'center',
              vertical_align: 'center',
              fadeout: 'off',
              maxVisibleItems: 3,
              infinity: 'on',
              space: 0,
              stretch: 'off',
              showLayersAllTime: 'off',
              easing: 'Power3.easeInOut',
              speed: '800'
            },
            responsiveLevels: [1240, 1024, 778, 480],
            visibilityLevels: [1240, 1024, 778, 480],
            gridwidth: [1240, 1024, 778, 480],
            gridheight: [700, 700, 500, 400],
            lazyType: 'none',
            parallax: {
              type: 'mouse',
              origo: 'slidercenter',
              speed: 2000,
              speedbg: 0,
              speedls: 0,
              levels: [2, 3, 4, 5, 25, 7, 12, 16, 10, 50, 47, 48, 49, 50, 51, 55]
            },
            shadow: 5,
            spinner: 'off',
            stopLoop: 'off',
            stopAfterLoops: -1,
            stopAtSlide: -1,
            shuffle: 'off',
            autoHeight: 'off',
            hideThumbsOnMobile: 'on',
            hideSliderAtLimit: 0,
            hideCaptionAtLimit: 0,
            hideAllCaptionAtLilmit: 0,
            debugMode: false,
            fallbacks: {
              simplifyAll: 'off',
              nextSlideOnWindowFocus: 'off',
              disableFocusListener: false
            }
          });
        }
      },
      'infoWindow': function() {
        $(window).on('load scroll resize', function () {
          var $infoWindow = $('.product-info .info');
          var height = $('#content').height();
          var difference = $(this).scrollTop() + $(this).height() - height;

          $infoWindow.css({
            'top': difference > 0 ? 'auto' : 40,
            'bottom': difference > 0 ? Math.abs(difference) : 'auto'
          });
        });
      },
      'search': function() {
        var search = $('.search');
        var searchBtn = $('.search-btn', search);
        var searchBlock = $('.search-block', search);

        searchBtn.on('click', function (e) {
          e.preventDefault();

          var el = $(this);

          if (!search.hasClass('open')) {
            search.addClass('open');
            searchBlock.fadeIn(200);
            $('input[name="q"]', search).focus();
          } else {
            search.removeClass('open');
            searchBlock.fadeOut(200);
          }
        });

        $(document).on('click', function (event) {
          if ($(event.target).closest(searchBlock).length === 0 && $(event.target).closest(searchBtn).length === 0) {
            search.removeClass('open');
            searchBlock.fadeOut(200);
          }
        });

        searchBlock.find('form').submit(function(e) {
          e.preventDefault();

          App.setPage({
            'url': $(this).attr('action') + '?' + $(this).serialize(),
            'title': $(this).attr('data-title')
          });
        });
      },
      'forms': function(form) {
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
      },

      // file loading check point
      _loadingQueue : false,

      // load file dynamically
      loadFile: function (src, callback) {
        if (!App._loadingQueue) {
          App._loadingQueue = true;

          var type = src.substr(src.lastIndexOf('.') + 1);

          if (type === 'js') {
            if (!$('script[src="' + src + '"]').get(0)) {
              $.when($.getScript(src)).then(function () {
                if (typeof callback === 'function') callback();

                App._loadingQueue = false;
              });
            } else {
              if (typeof callback === 'function') callback();

              App._loadingQueue = false;
            }
          } else if (type === 'css') {
            if (!$('link[href="' + src + '"]').get(0)) {
              $.when(
                $('<link/>', {
                  'rel': 'stylesheet',
                  'type': 'text/css',
                  'href': src
                }).insertBefore($('link[href^="' + App.root + 'css/"]:first'))
              ).then(function () {
                if (typeof callback === 'function') callback();

                App._loadingQueue = false;
              });
            } else {
              if (typeof callback === 'function') callback();

              App._loadingQueue = false;
            }
          }
        } else {
          window.setTimeout(function () {
            App.loadFile(src, callback);
          }, 100);
        }
      },

      // multiple file index
      _loadingQueueIndex: 0,

      // load multiple files dynamically
      loadFiles: function(srcs, callback) {
        if ($.isArray(srcs) && srcs.length) {
          $.when(App.loadFile(srcs[App._loadingQueueIndex], App._loadingQueueIndex + 1 === srcs.length ? callback : null)).then(function() {
            App._loadingQueueIndex++;

            if (App._loadingQueueIndex < srcs.length) {
              App.loadFiles(srcs, callback);
            } else {
              App._loadingQueueIndex = 0;
            }
          });
        }
      }
    };
  }();

  $(function() {
    App.init();


  });
})(jQuery);
