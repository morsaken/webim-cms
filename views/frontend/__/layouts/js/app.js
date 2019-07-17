(function($) {
  'use strict';

  var App = function () {
    return {
      // File root
      root: $('base').attr('data-root'),

      // timers
      timers: {},

      // Initialize
      init: function () {
        console.log('%cPowered By Masters — Orhan POLAT', 'padding:8px 35px; color:#fff; background-color:#2B3643; line-height:25px;');

        $(document).ajaxStart(function () {
          App.loader(true);
        }).ajaxStop(function () {
          App.loader(false);
        });

        App.menuToggler();
        App.popState();
        App.search();
        App.plugins();
      },

      plugins: function() {
        $('[data-background-image]', '#content').each(function() {
          var img = $(this).data('background-image');

          if (img) {
            $(this).removeAttr('data-background-image').css('background-image', 'url(' + img + ')');
          }
        });

        $('[data-toggler]').click(function (e) {
          e.preventDefault();

          if ($(this).closest('ul').hasClass('level-0')) {
            $('.banner .level-0 ul.tree, .banner .level-1 ul.tree, .banner .level2 ul.tree').slideUp();
          }

          $(this).parent().children('ul.tree').toggle(300);
        });

        $('[data-school]').click(function(e) {
          e.preventDefault();

          $.ajax({
            method: 'post',
            url: $('base').attr('href') + '/set-school',
            data: {
              'current': $(this).attr('data-school')
            }
          }).done(function(result) {
            if (result.success) {
              window.location.href = $('base').attr('href');
            }
          });
        });

        $('img.lazy').removeClass('img-responsive').lazyload({
          'placeholder': '',
          'load': function() {
            $(this).addClass('img-responsive');
          }
        });

        if ($('#tweets').length) {
          App.load.js(App.root + '/plugins/jquery.tweet.min.js').then(function() {
            $('#tweets').tweet({
              modpath : $('#tweets').data('url'),
              username: 'anonymous',
              loading_text : $('#tweets').data('loading-text'),
              template : '{avatar}{join}{text}{time}'
            });
          });

          $(document).on('click', '#tweets a', function(e) {
            e.preventDefault();

            window.open($(this).attr('href'), 'twitter');
          });
        }

        if ($('.owl-carousel').length) {
          App.load.js(App.root + '/plugins/owl-carousel/owl.carousel.min.js').then(function() {
            if ($().owlCarousel) {
              var rtl = ($('html').attr('dir') === 'rtl');

              $('.owl-carousel', '#content').each(function () {
                var $this = $(this);

                $this.owlCarousel({
                  rtl: rtl,
                  autoplay: !!$this.data('autoplay'),
                  autoplayHoverPause: !!$this.data('autoplay-hover-pause'),
                  items: ($this.data('items') || 1),
                  lazyLoad: !!$this.data('lazy-load'),
                  dots: !!$this.data('dots'),
                  nav: !!$this.data('nav'),
                  margin: ($this.data('margin') || 0),
                  loop: !!$this.data('loop')
                });
              })
            }
          });
        }

        $('form:not([data-title])').each(function() {
          var callback;

          if ($(this).closest('.modal').length) {
            callback = function () {
              $(this).closest('.modal').modal('hide');
            };
          }

          App.forms(this, callback);
        });

        $('.grecaptcha-badge').parent().hide();

        $('input[name="g-recaptcha-response"]').each(function() {
          App.load.js('//www.google.com/recaptcha/api.js?hl=' + $('html').attr('lang') + '&onload=googleCaptcha&render=' + $(this).attr('data-site-key')).then(function(result) {
            if (result.exists) {
              $('.grecaptcha-badge').parent().show();

              if (typeof googleCaptcha === 'function') {
                googleCaptcha();
              }
            }

          });
        });

        App.maps();
      },

      loader: function(show) {
        if (App.timers['loader']) {
          window.clearTimeout(App.timers['loader']);
          delete App.timers['loader'];
        }

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

          App.timers['loader'] = window.setTimeout(function() {
            App.loader(false);
          }, 30000);
        } else {
          $.unblockUI();
        }
      },

      menuToggler: function() {
        var target = $('a.nav-toggle');

        target.on('click', function(e) {
          e.preventDefault();

          if ($('body').hasClass('open')) {
            $('body').removeClass('open');
          } else {
            $('body').addClass('open');
          }
        });

        var container = $('.mobile-nav-container');

        $('.menu a', container).click(function() {
          $('.menu a', container).removeClass('active');
          $(this).addClass('active');
          $('body').removeClass('open');
        });

        $('form', container).submit(function() {
          $('body').removeClass('open');
        });
      },

      popState: function() {
        if (window.history.pushState) {
          var root = window.location.origin;
          var selector = 'a[href^="' + root + '"]:not(.no-ajax):not([target]),'
            + 'a[href^="/"]:not(.no-ajax):not([target])';

          $('body').on('click', selector, function (e) {
            e.preventDefault();

            var url = $(this).attr('href').replace(root, '');
            var path = window.location.pathname;
            var title = $(this).attr('data-title');

            if (!url.length) {
              url = '/';
            }

            if (!title) {
              title = $(this).attr('title');

              if (!title) {
                title = $(this).text();
              }
            }

            if (url !== path) {
              App.setPage({
                'url': url,
                'title': title.trim()
              });
            }
          });

          window.onpopstate = function (e) {
            if (e.state) {
              App.setPage({
                'url': e.state.url,
                'title': e.state.title
              }, true);
            }
          };
        }
      },

      setPage: function(page, fromHistory) {
        App.loader(true);

        var nav = $('.navbar-nav');
        var root = window.location.origin;
        var home = $('base').attr('href').toString().replace(root, '');

        if (!home.length) {
          home = '/';
        }

        nav.find('.nav-item, .dropdown-item').removeClass('active');
        nav.find('a[href]:not([href^="#"]):not([href^="javascript:"])').each(function() {
          var uri = null;

          if ($(this).attr('href')) {
            uri = $(this).attr('href').replace(root, '');
          }

          if ($(this).attr('data-href')) {
            uri = $(this).attr('data-href').replace(root, '');
          }

          if ((uri === page.url) || (uri && uri.length && page.url.indexOf(uri) === 0)) {
            $(this).closest('.dropdown-item').addClass('active');
            $(this).closest('.nav-item').addClass('active');
          }
        });

        var $title = $('title'),
          title = $title.attr('data-default'),
          separator = $title.attr('data-separator');

        if (page.title && page.title.length && (page.title !== title) && (page.url !== '') && (page.url !== home)) {
          title = page.title + ' ' + separator + ' ' + title;
        }

        $title.text(title);

        if (window.history.pushState && !fromHistory) {
          window.history.pushState({
            'url': page.url,
            'title': page.title
          }, page.title, page.url);
        }

        $('#content').load(page.url, function(response, status, xhr) {
          $('html, body').scrollTop(0);

          if (status === 'error') {
            $('#content').html(response);
          }

          App.loader(false);

          window.setTimeout(function() {
            App.plugins();
          }, 500);
        });
      },

      search: function() {
        $('form.search-form').submit(function(e) {
          e.preventDefault();

          App.setPage({
            'url': $(this).attr('action') + '?' + $(this).serialize(),
            'title': $(this).attr('data-title')
          });
        });
      },

      forms: function(form, callback) {
        var captcha = $('img.captcha', form).on('click', function() {
          reloadCaptcha();
        });

        var reloadCaptcha = function() {
          if (typeof googleCaptcha === 'function') {
            googleCaptcha();
          } else if (captcha.length) {
            $('input[name="captcha"]').val('');
            captcha.attr('src', captcha.attr('src').replace(/\?.*$/, '') + '?rnd=' + Math.random());
          }
        };

        $(form).find('input, select, textarea').on('change invalid', function() {
          var tagName = $(this).prop('tagName');
          var field = $(this).get(0);

          field.setCustomValidity('');

          if (!field.validity.valid) {
            var message = 'Lütfen işaretli yerleri doldurunuz';

            if (tagName === 'SELECT') {
              message = 'Lütfen bir seçenek belirtiniz';
            }

            field.setCustomValidity($(this).data('message') || message);
          }
        });

        $(form).off('submit').on('submit', function(e) {
          e.preventDefault();

          var form = $(this);
          var btn = $('[type="submit"]', form);
          btn.prop('disabled', true);

          var formData = new FormData(this);

          $.ajax({
            'type': form.attr('method'),
            'url': form.attr('action'),
            'processData': false,
            'dataType': 'json',
            'contentType': false,
            'cache': false,
            'data': formData
          }).done(function(result) {
            if (result.success) {
              $('input[type="text"],' +
                'input[type="email"],' +
                'input[type="tel"],' +
                'input[type="file"],' +
                'input[type="url"],' +
                'select,' +
                'textarea', form).val('');
            }

            toastr[(result.success ? 'success' : 'error')](result.text);

            btn.prop('disabled', false);

            reloadCaptcha();

            if (typeof callback === 'function') {
              callback(result);
            }
          }).fail(function(response, status, xhr) {
            toastr.error(response.statusText);
            btn.prop('disabled', false);

            reloadCaptcha();
          });
        });
      },

      maps: function () {
        if ($('.map').length) {
          var key = $('.maps').attr('data-key');

          App.load.js('//maps.google.com/maps/api/js?key=' + key).then(function() {
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

            var styles = [{
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
            }, {'featureType': 'water', 'elementType': 'all', 'stylers': [{'color': '#d2e7f7'}]}];

            $('.map').each(function() {
              var $map = $(this).find('[data-lat]');

              var mapLatLon = {
                lng: $map.data('lon'),
                lat: $map.data('lat')
              };

              var map = new google.maps.Map($map.get(0), {
                zoom: $map.data('zoom') || 14,
                center: mapLatLon,
                scrollwheel: false,
                zoomControl: false,
                mapTypeControl: false,
                scaleControl: false,
                panControl: false,
                navigationControl: false,
                streetViewControl: false,
                styles: styles
              });

              if ($map.data('marker-lon')) {
                var marker = new google.maps.Marker({
                  position: {
                    lng: $map.data('marker-lon'),
                    lat: $map.data('marker-lat')
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

                var state = $(this).data('state') === 'street' ? 'vector' : 'street';

                map.getStreetView().setOptions({
                  visible: (state === 'street'),
                  position: mapLatLon
                });

                $(this).data('state', state).text($(this).data(state + '-text'));
              });
            });
          });
        }
      },

      // Currently loading files
      loadings: {},

      // Load files dynamically
      load: (function () {
        var _load = function (tag) {
          return function (url) {
            if (App.loadings[url]) {
              return new Promise(function(resolve, reject) {
                window.setTimeout(function() {
                  var key = 'img';
                  if (tag === 'script') key = 'js';
                  if (tag === 'link') key = 'css';

                  App.load[key](url).then(function(el) {
                    resolve(el);
                  }).catch(function(e) {
                    reject(e);
                  });
                }, 200);
              });
            }

            // Add to loading queue
            App.loadings[url] = true;

            // Existing files
            var files = {};

            $(tag).each(function() {
              var url;

              if (tag === 'link') {
                url = $(this).attr('href');
              } else {
                url = $(this).attr('src');
              }

              if (typeof files[url] === 'undefined') {
                files[url] = this;
              }
            });

            // This promise will be used by Promise.all to determine success or failure
            return new Promise(function (resolve, reject) {
              var el = document.createElement(tag), parent = 'body', attr = 'src', before;

              if (typeof files[url] !== 'undefined') {
                delete App.loadings[url];

                resolve({
                  'el': files[url],
                  'exists': true
                });
              } else {
                // Important success and error for the promise
                el.onload = function () {
                  delete App.loadings[url];

                  resolve({
                    'el': el
                  });
                };

                el.onerror = function () {
                  delete App.loadings[url];

                  reject(new Error('File not found: ' + url));
                };

                // Need to set different attributes depending on tag type
                switch (tag) {
                  case 'script':
                    el.type = 'text/javascript';
                    el.async = true;
                    before = document.querySelector('script[src^="' + App.root + 'js"]');
                    break;
                  case 'link':
                    el.type = 'text/css';
                    el.rel = 'stylesheet';
                    attr = 'href';
                    parent = 'head';
                    before = document.querySelector('link[href^="' + App.root + 'css"]');
                    break;
                  case 'img':
                    el.alt = '';
                }

                // Inject into document to kick off loading
                el[attr] = url;

                if (before) {
                  document[parent].insertBefore(el, before);
                } else {
                  document[parent].appendChild(el);
                }
              }
            });
          };
        };

        return {
          css: _load('link'),
          js: _load('script'),
          img: _load('img')
        }
      })(),

      // load multiple files dynamically
      loadFiles: function(srcs, callback) {
        if ($.isArray(srcs) && srcs.length) {
          var loads = [];

          srcs.forEach(function(src) {
            var type = src.substr(src.lastIndexOf('.') + 1);

            switch (type) {
              case 'js':
                loads.push(App.load.js(src));
                break;
              case 'css':
                loads.push(App.load.css(src));
                break;
              case 'img':
                loads.push(App.load.img(src));
                break;
            }
          });

          Promise.all(loads).then(callback).catch(function(e) {
            console.log('Load error: ' + e.message);
          });
        }
      }
    };
  }();

  $(function() {
    App.init();
  });
})(jQuery);