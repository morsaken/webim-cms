'use strict';

/**
 * Language text
 */

if (!$.lang) {
  $.lang = {
    'yes': 'Evet',
    'no': 'Hayır',
    'ok': 'Tamam',
    'cancel': 'İptal',
    'message': 'Mesaj',
    'close': 'Kapat',
    'please-wait': 'Lütfen bekleyin...',
    'loading': 'Yükleniyor...',
    'ajax-error': 'AJAX sonuç hatası oluştu!',
    'check-at-least-one': 'En az bir kayıt seçin!',
    'sure-to-delete': 'Kaydı silmek istediğinize emin misiniz?',
    'sure-to-delete-all': 'Seçili kayıtları silmek istediğinize emin misiniz?',
    'not-found': 'Bulunamadı'
  };
}

function lang(key, default_str) {
  //Return string
  var str = default_str || key;

  if ($.lang && $.lang[key]) {
    str = $.lang[key];

    if (arguments.length > 1) {
      for (var i = 1; i < arguments.length; i++) {
        str = str.replace('%s', arguments[i]);
      }
    }
  }

  return str;
}

/**
 * Message dialog
 */
function message() {
  var config = arguments[0] || {};

  var content = '<div id="message-dialog" class="modal fade">\
 <div class="modal-dialog alerter">\
 <div class="modal-content">\
 <div class="modal-header">\
 <button type="button" name="close" class="close" data-dismiss="modal">×</button>\
 <h4><i class="fa fa-warning"></i> ' + (config.title || lang('message')) + '</h4>\
 </div>\
 <div class="modal-body">\
 <div id="message-text"><p>' + (config.text || config.toString()) + '</p></div>\
 </div>\
 <div class="modal-footer">';

  if (config.type && (config.type == 'confirm')) {
    content += '<button type="button" class="btn btn-primary btn-sm" data-dismiss="modal" data-confirm="modal">\
  <i class="fa fa-check"></i> ' + lang('yes') + '\
  </button>\
  <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">\
  <i class="fa fa-remove"></i> ' + lang('no') + '\
  </button>';
  } else {
    content += '<button type="button" class="btn btn-primary btn-sm" data-dismiss="modal">\
  <i class="fa fa-check"></i> ' + lang('ok') + '\
  </button>';
  }

  content += '</div>\
 </div>\
 </div>\
 </div>';

  var html = $(content).appendTo('body');

  if (config.type && (config.type == 'confirm') && $.isFunction(config.onConfirm)) {
    $('[data-confirm="modal"]', html).click(function () {
      config.onAfterClose = config.onConfirm;
    });
  }

  html.modal().on('shown.bs.modal', function () {
    $(this).find('button').first().focus();
  }).on('hidden.bs.modal', function () {
    html.remove();

    if ($.isFunction(config.onAfterClose)) {
      config.onAfterClose();
    }
  });

  return html;
}

var root = $('base').attr('href');

var loadings = {};

// Dynamically load
var load = (function () {
  var _load = function (tag) {
    return function (url) {
      if (loadings[url]) {
        return new Promise(function(resolve, reject) {
          window.setTimeout(function() {
            var key = 'img';
            if (tag === 'script') key = 'js';
            if (tag === 'link') key = 'css';

            load[key](url).then(function(el) {
              resolve(el);
            }).catch(function(e) {
              reject(e);
            });
          }, 200);
        });
      }

      loadings[url] = true;

      // Existing files
      var files = {};

      $(tag).each(function () {
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
          delete loadings[url];

          resolve(files[url]);
        } else {
          // Important success and error for the promise
          el.onload = function () {
            delete loadings[url];

            resolve(el);
          };

          el.onerror = function () {
            delete loadings[url];

            reject('File not found: ' + url);
          };

          // Need to set different attributes depending on tag type
          switch (tag) {
            case 'script':
              el.type = 'text/javascript';
              el.async = true;
              before = document.querySelector('script[src^="' + root + 'js/webim.js"]');
              break;
            case 'link':
              el.type = 'text/css';
              el.rel = 'stylesheet';
              attr = 'href';
              parent = 'head';
              before = document.querySelector('link[href^="' + root + 'css"]');
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
  };
})();


function resetForm(form) {
  $(form).find('input:not([type="radio"]):not([type="checkbox"]), select, textarea').val('');
  $(form).find('input[type="radio"]').first().prop('checked', true);
  $(form).find('input[type="checkbox"]').prop('checked', false);
}

function setValue(name, value) {
  if ($('input[name="' + name + '"]').length) {
    if ($('input[name="' + name + '"]').is(':radio') || $('input[name="' + name + '"]').is(':checkbox')) {
      $('input[name="' + name + '"][value="' + value + '"]').prop('checked', true);
    } else {
      $('input[name="' + name + '"]').val(value ? value : '');
    }
  } else if ($('select[name="' + name + '"]').length) {
    if ($.isArray(value)) {
      $.each(value, function (k, v) {
        $('select[name="' + name + '"] > option[value="' + v + '"]').prop('selected', true);
      });
    } else {
      $('select[name="' + name + '"]').val(value);
    }
  } else if ($('textarea[name="' + name + '"]').length) {
    $('textarea[name="' + name + '"]').val(value);
  }
}

if ($.blockUI) {
  $(document).ajaxStart(function () {
    $.blockUI({
      'css': {
        backgroundColor: '#f2f2f2',
        color: '#000000',
        border: 'none',
        padding: '10px',
        fontSize: '16px'
      },
      'baseZ': 10000,
      'message': '<i class="fa fa-spinner fa-spin"></i> ' + lang('please-wait')
    });
  }).ajaxStop($.unblockUI).ajaxError(function () {
    message({
      'type': 'forbidden',
      'text': lang('ajax-error')
    });
  });
}

console.log('%cPowered By Masters — Orhan POLAT', 'padding:8px 35px; color:#fff; background-color:#2B3643; line-height:25px;');

//Ajax setup
$.ajaxSetup({
  type: 'POST',
  abortOnRetry: true
});

//Current requests
var currentRequests = {};

//Filter requests
$.ajaxPrefilter(function (options, originalOptions, jqXHR) {
  if (options.abortOnRetry) {
    if (currentRequests[options.url]) {
      currentRequests[options.url].abort();
    }

    currentRequests[options.url] = jqXHR;
  }
});

if ($.ui) {
  $.extend($.ui.dialog.prototype.options, {
    modal: true,
    resizable: false,
    draggable: true,
    width: 400
  });
}

/**
 * Normalize string
 */
(function ($) {
  $.normalizeString = function (str, except) {
    var delimiter = '-';

    str = $.trim(str);

    if (!except || !except.length) {
      except = delimiter;
    }

    var chrs = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü', '_', ' ', '/'];
    var chns = ['c', 'g', 'i', 'o', 's', 'u', 'C', 'G', 'I', 'O', 'S', 'U', delimiter, delimiter, delimiter];
    var i = 0, x = 0, y = 0, z = 0, letters, splitted;

    for (x = 0; x < except.length; x++) {
      letters = str.split(except[x]);

      for (z = 0; z < letters.length; z++) {
        for (i = 0; i < letters[z].length; i++) {
          for (y = 0; y < chrs.length; y++) {
            if (letters[z][i] == chrs[y]) {
              splitted = letters[z].split(chrs[y]);
              letters[z] = splitted.join(chns[y]);
            }
          }
        }

        letters[z] = letters[z].toLowerCase().replace(/[^a-zA-Z0-9_\-]/g, '');
      }

      str = letters.join(except[x]);
    }

    return str;
  };
})(jQuery);

(function ($) {
  var lang = $('html').attr('lang');
  moment.locale(lang);
  //moment.tz.setDefault('Europe/Istanbul');

  $('input[name="check-all"]', '#list > table').click(function () {
    $('input[name="check[]"]', '#list > table').click(function () {
      $('input[name="check-all"]', '#list > table').prop('checked',
        $('input[name="check[]"]:checked', '#list > table').length == $('input[name="check[]"]', '#list > table').length
      );
    }).prop('checked', $(this).prop('checked'));
  });
})(jQuery);

