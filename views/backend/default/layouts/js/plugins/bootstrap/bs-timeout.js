'use strict';
(function ($) {
  jQuery.sessionTimeout = function (options) {
    var defaults = {
      title: 'Your Session is About to Expire!',
      message: 'Your session is about to expire.',
      logoutButton: 'Logout',
      keepAliveButton: 'Stay Connected',
      keepAliveUrl: '/keep-alive',
      ajaxType: 'POST',
      ajaxData: '',
      redirUrl: '/timed-out',
      logoutUrl: '/log-out',
      warnAfter: 900000,
      keepAliveInterval: 5000,
      keepAlive: true,
      ignoreUserActivity: false,
      onStart: false,
      onWarn: false,
      onRedir: false,
      countFrom: 60000,
      countdownMessage: false,
      countdownBar: false
    };
    var opt = defaults, timer, countdown = {};
    if (options) {
      opt = $.extend(defaults, options);
    }
    if (typeof opt.onWarn !== 'function') {
      var countdownMessage = opt.countdownMessage ? '<p>' + opt.countdownMessage.replace(/{timer}/g, '<span class="countdown-holder"></span>') + '</p>' : '';
      var coundownBarHtml = opt.countdownBar ? '<div class="progress"> \
                  <div class="progress-bar progress-bar-striped countdown-bar active" role="progressbar" style="min-width: 15px; width: 100%;"> \
                    <span class="countdown-holder"></span><span>s</span> \
                  </div> \
                </div>' : '';
      $('body').append('<div class="modal fade" id="session-timeout-dialog"> \
              <div class="modal-dialog"> \
                <div class="modal-content"> \
                  <div class="modal-header"> \
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button> \
                    <h4 class="modal-title">' + opt.title + '</h4> \
                  </div> \
                  <div class="modal-body"> \
                    <p>' + opt.message + '</p> \
                    ' + countdownMessage + ' \
                    ' + coundownBarHtml + ' \
                  </div> \
                  <div class="modal-footer"> \
                    <button id="session-timeout-dialog-logout" type="button" class="btn btn-default">' + opt.logoutButton + '</button> \
                    <button id="session-timeout-dialog-keepalive" type="button" class="btn btn-primary" data-dismiss="modal">' + opt.keepAliveButton + '</button> \
                  </div> \
                </div> \
              </div> \
             </div>');
      $('#session-timeout-dialog-logout').on('click', function () {
        window.location = opt.logoutUrl;
      });
      $('#session-timeout-dialog').on('hide.bs.modal', function () {
        startSessionTimer();
      });
    }
    if (!opt.ignoreUserActivity) {
      $(document).on('keyup mouseup mousemove touchend touchmove', function () {
        startSessionTimer();
      });
    }
    var keepAlivePinged = false;

    function keepAlive() {
      if (!keepAlivePinged) {
        $.ajax({type: opt.ajaxType, url: opt.keepAliveUrl, data: opt.ajaxData, global: false});
        keepAlivePinged = true;
        setTimeout(function () {
          keepAlivePinged = false;
        }, opt.keepAliveInterval);
      }
    }

    function startSessionTimer() {
      clearTimeout(timer);
      if (opt.countdownMessage || opt.countdownBar) {
        startCountdownTimer('session', true);
      }
      if (typeof opt.onStart === 'function') {
        opt.onStart(opt);
      }
      if (opt.keepAlive) {
        keepAlive();
      }
      timer = setTimeout(function () {
        if (typeof opt.onWarn !== 'function') {
          $('#session-timeout-dialog').modal('show');
        } else {
          opt.onWarn(opt);
        }
        startDialogTimer();
      }, opt.warnAfter);
    }

    function startDialogTimer() {
      clearTimeout(timer);
      if (!$('#session-timeout-dialog').hasClass('in') && (opt.countdownMessage || opt.countdownBar)) {
        startCountdownTimer('dialog', true);
      }
      timer = setTimeout(function () {
        if (typeof opt.onRedir !== 'function') {
          window.location = opt.redirUrl;
        } else {
          opt.onRedir(opt);
        }
      }, opt.countFrom);
    }

    function startCountdownTimer(type, reset) {
      clearTimeout(countdown.timer);
      if (reset) {
        countdown.timeLeft = Math.floor(opt.countFrom / 1000);
      }
      if (opt.countdownBar) {
        countdown.percentLeft = Math.floor(countdown.timeLeft / (opt.countFrom / 1000) * 100);
        $('.countdown-bar').css('width', countdown.percentLeft + '%');
      }
      $('.countdown-holder').text(countdown.timeLeft);
      countdown.timeLeft = countdown.timeLeft - 1;
      countdown.timer = setTimeout(function () {
        startCountdownTimer(type);
      }, 1000);
    }

    startSessionTimer();
  };
})(jQuery);