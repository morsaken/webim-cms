(function($) {
  'use strict';

  var App = function () {
    return {
      'history': [],
      'root': $('base').attr('href'),
      'init': function () {
        var $title = $('title'),
          title = $title.text().replace(' | ' + $title.attr('data-default'), '');

        App.history.push({
          'url': window.location.href,
          'title': title.length ? title : $title.attr('data-default')
        });

        $(document).ajaxStart(function () {
          App.loader(true);
        }).ajaxStop(function () {
          App.loader(false);
        });

        App.menuToggler();
        App.scroller();
        App.fitHeight('.blocks');
        App.popState();
        App.fixTop();
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

        var stickyTimer;

        window.setTimeout(function() {
          if ($('.sticky').hasClass('closed')) {
            $('.sticky').trigger('mouseover');

            stickyTimer = window.setTimeout(function () {
              $('.sticky').trigger('mouseout');
            }, 5000);
          }
        }, 10000);

        $('.sticky').hover(function() {
          $(this).removeClass('closed');

          if (stickyTimer) {
            window.clearInterval(stickyTimer);
          }
        }, function() {
          $(this).addClass('closed');
        });

        $('img.lazy').removeClass('img-responsive').lazyload({
          'placeholder': '',
          'load': function() {
            $(this).addClass('img-responsive');
          }
        });

        App.divScroller();

        App.selecty();

        if ($('#slider').length) {
          App.revSlider('#slider');
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

                if (val != '*') {
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
            fillColor: '#8DC63F',
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
        var target = $('.menu a.menu-toggle');

        target.click(function(e) {
          e.preventDefault();

          if (menu.hasClass('open')) {
            menu.removeClass('open');
          } else {
            menu.addClass('open');
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
          $('a.pop', context).click(function (e) {
            e.preventDefault();

            var cur = window.location.origin + window.location.pathname;
            var url = $(this).attr('href');
            var title = $(this).attr('title');

            if (url.length && (url !== '#') && (url !== cur) && !$(this).prop('target')) {
              App.setPage({
                'url': url,
                'title': title
              });
            }
          });

          window.onpopstate = function (e) {
            App.setPage({
              'url': e.state ? e.state.url : null
            });
          };
        }
      },
      'setPage': function(page, fromHistory) {
        App.loader(true);

        var root = $('a.logo').attr('href');
        var path = page.url.replace(root, '');

        $('.menu nav').find('a').removeClass('active').closest('.menu nav').find('a').each(function() {
          var uri = $(this).attr('href').replace(root, '');

          if ((uri === path) || (uri.length && path.indexOf(uri) === 0)) {
            $(this).addClass('active');
          }
        });

        var $title = $('title'),
          title = $title.attr('data-default');

        if (page.title && page.title.length && (page.title !== title)) {
          title = page.title + ' | ' + title;
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

          if (status == 'error') {
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
      },
      'scroller': function () {
        $('.menu').niceScroll({
          cursorcolor: '#8DC63F',
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
          cursorcolor: '#8DC63F',
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
        if ($(target).revolution == undefined){
          revslider_showDoubleJqueryError(target);
        } else {
          $(target).revolution({
            sliderType: 'standard',
            jsFileLocation: (jsPath || App.root + '/js/plugins/rs-plugin/js/'),
            sliderLayout: 'auto',
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
                style: 'hesperiden',
                enable: true,
                hide_onmobile: true,
                hide_under: 778,
                hide_onleave: true,
                hide_delay: 200,
                hide_delay_mobile: 200,
                tmp: '',
                left: {
                  h_align: 'left',
                  v_align: 'center',
                  h_offset: 20,
                  v_offset: 0
                },
                right: {
                  h_align: 'right',
                  v_align: 'center',
                  h_offset: 20,
                  v_offset: 0
                }
              },
              thumbnails: {
                style: 'gyges',
                enable: true,
                width: 60,
                height: 60,
                min_width: 60,
                wrapper_padding: 5,
                wrapper_color: 'rgba(34,34,34,1)',
                tmp: '<span class="tp-thumb-img-wrap"><span class="tp-thumb-image"></span></span>',
                visibleAmount: 5,
                hide_onmobile: false,
                hide_over: 777,
                hide_onleave: false,
                direction: 'vertical',
                span: true,
                position: 'outer-left',
                space: 5,
                h_align: 'left',
                v_align: 'top',
                h_offset: 0,
                v_offset: 0
              },
              tabs: {
                style: 'gyges',
                enable: true,
                width: 200,
                height: 80,
                min_width: 250,
                wrapper_padding: 15,
                wrapper_color: 'rgba(61,61,61,1)',
                tmp: '<div class="tp-tab-content"><span class="tp-tab-title">{{title}}</span></div><div class="tp-tab-image"></div>',
                visibleAmount: 10,
                hide_onmobile: true,
                hide_under: 778,
                hide_onleave: false,
                hide_delay: 200,
                direction: 'vertical',
                span: true,
                position: 'outer-left',
                space: 0,
                h_align: 'left',
                v_align: 'top',
                h_offset: 0,
                v_offset: 0
              }
            },
            visibilityLevels: [1240, 1024, 778, 480],
            gridwidth: 800,
            gridheight: 640,
            lazyType: 'single',
            shadow: 0,
            spinner: 'spinner2',
            stopLoop: 'on',
            shuffle: 'off',
            autoHeight: 'off',
            disableProgressBar: 'on',
            hideThumbsOnMobile: 'off',
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
      'fixTop': function() {
        $(window).on('load scroll', function () {
          if ($(window).scrollTop() >= $('.top').height() * 2) {
            if (!$('body').hasClass('fixed')) $('body').addClass('fixed');
          } else {
            $('body').removeClass('fixed');
          }
        });
      },
      'selecty': function() {
        $('select.cs-select').each(function() {
          new SelectFx(this);
        });
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
        $('#search-form').submit(function(e) {
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
            toastr['error'](response.statusText);
            btn.prop('disabled', false);
          });
        });
      }
    };
  }();

  $(function() {
    App.init();
  });
})(jQuery);
