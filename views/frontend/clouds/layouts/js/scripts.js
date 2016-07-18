/**
 * Language text
 */
if (!$.lang){
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

 if ($.lang && $.lang[key]){
  str = $.lang[key];

  if (arguments.length > 1){
   for (var i = 1; i < arguments.length; i++){
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
 var types = [
  'info',
  'error',
  'ok',
  'warning',
  'forbidden',
  'delete',
  'caution',
  'confirm',
  'find'
 ];
 var type = (config.type || 'info');

 if ($.inArray(type, types) < 0){
  type = 'info';
 }

 var labels = config.labels || {};

 var buttons = [];

 if (type == 'confirm'){
  buttons.push({
   text: (labels.yes || lang('yes')),
   click: function(){
    $(this).dialog('close');
    if ($.isFunction(config.onConfirm)){
     config.onConfirm();
    }
   }
  });
  buttons.push({
   text: (labels.no || lang('no')),
   click: function(){
    $(this).dialog('close');
    if ($.isFunction(config.onReject)){
     config.onReject();
    }
   }
  });
 }else{
  buttons.push({
   text: (labels.ok || lang('ok')),
   click: function(){
    $(this).dialog('close');
   }
  });
 }

 var html = $('<table/>').attr('width', '100%').append(
  $('<tbody/>').append(
   $('<tr/>').append(
    $('<td/>').addClass('ui-message').append(
     $('<span/>').addClass('ui-message-icon ui-message-'+ type)
    ).append(
     $('<span/>').html(config.text || config.toString())
    )
   )
  )
 ).dialog({
   modal: true,
   resizable: false,
   title: (config.title || lang('message')),
   closeText: (config.closeText || lang('close')),
   width: 320,
   position: 'center',
   buttons: buttons,
   close: function(){
    html.remove();
    if ($.isFunction(config.onAfterClose)){
     config.onAfterClose();
    }
   }
  });

 return html;
}

function redirectURL(obj) {
 if (obj && obj.href) {
  window.open(obj.href);
 }
 return false;
}

$(document).ready(function () {
 $('#far-clouds').pan({
  fps: 30,
  speed: 0.7,
  dir: 'left'
 });
 $('#near-clouds').pan({
  fps: 30,
  speed: 1,
  dir: 'left',
  depth: 70
 });

 var date = new Date($('body').data('date'));

 $('#countdown_dashboard').countDown({
  targetDate: {
   'day': date.getDate(),
   'month': date.getMonth() + 1,
   'year': date.getFullYear(),
   'hour': date.getHours(),
   'min': date.getMinutes(),
   'sec': date.getSeconds()
  },
  omitWeeks: true
 });

 $('#newsletter-btn').click(function () {
  $('#dialog').dialog({
   'resizable': false,
   'width': 500,
   'position': ['center', 'center'],
   'title': 'E-Posta Adresiniz',
   'open': function () {
    $('#email, #code').val('');
   },
   'buttons': {
    'Gönder': function () {
     var reg = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

     if (reg.test($('#email').val())) {
      $.ajax({
       'type': 'POST',
       'url': $('#dialog-form').attr('action'),
       'data': $('#dialog-form').serialize()
      }).done(function (result) {
       if (result && result.text) {
        message({
         'type': (result.success ? 'ok' : 'error'),
         'text': result.text,
         'onAfterClose': function () {
          if (result.success) {
           $('#dialog').dialog('close');
          }
         }
        });
       }

       $('#captcha').attr('src', $('#captcha').attr('src').replace(/\?.*$/, '') + '?rnd=' + Math.random());
      });
     } else {
      message({
       'type': 'warning',
       'text': 'E-posta adresi geçersiz!',
       'onAfterClose': function () {
        $('#email').select();
       }
      })
     }
    },
    'İptal': function () {
     $('#dialog').dialog('close');
    }
   }
  });
 });
});
