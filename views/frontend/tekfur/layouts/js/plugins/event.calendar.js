(function ($) {
  /**
   * Get element data
   *
   * @param el
   * @param key
   * @param def
   * @returns {*}
   */
  function getData(el, key, def) {
    var data = el.attr('data-' + key);

    if (data) {
      return data;
    }

    return def;
  }

  $.fn.eventCalendar = function (options) {
    var date = new Date(),
      currentMonth = date.getMonth() + 1,
      currentYear = date.getFullYear();

    var el = this,
      defaults = {
        events: [],
        eventsContainer: '#event-list',
        eventsUrl: getData(el, 'url', '/'),
        months: JSON.parse(getData(el, 'months', '[]')),
        days: JSON.parse(getData(el, 'days', '[]')),
        message: {
          empty: getData(el, 'empty-message', 'No event found!'),
          error: getData(el, 'error-message', 'An error occured!')
        },
        label: {
          more: getData(el, 'more-label', 'More')
        },
        currentMonth: currentMonth, // default current month
        currentYear: currentYear, // default current year
        startMonth: currentMonth, // month of min. date (default current month)
        startYear: currentYear, // year of min. date (default current year)
        endMonth: currentMonth, // month of max. date (default current month of next year)
        endYear: currentYear + 1, // year of max. date (default next year)
        firstDayOfWeek: parseFloat(getData(el, 'first-day-of-week', 0)),
        onBeforeLoad: function () {
        }, // event: before eventCalendar starts loading
        onAfterLoad: function () {
        }, // event: after eventCalendar loaded
        onMonthClick: function () {
        }, // event: click on next or prev month
        onDayClick: function () {
        } // event: click on an event day
      };
    options = $.extend({}, defaults, options);

    /* initialize elements and view */
    this.init = function () {
      options.onBeforeLoad();

      if (el.find('.months').length == 0 && el.find('.days').length == 0) {
        el.html('<div class="months">' +
          '<a href="javascript:;" class="change-month prev-month" data-month="" data-year=""></a>' +
          '<a href="javascript:;" class="current-month" data-month="" data-year=""></span>' +
          '<a href="javascript:;" class="change-month next-month " data-month="" data-year=""></a>' +
          '</div>' +
          '<div class="days"></div>');
      }

      if (!$(options.eventsContainer).length) {
        el.after('<div id="event-list"></div>');
      }

      this.initMonths();
      this.initDays();
    };

    /* initialize months */
    this.initMonths = function () {
      var monthsWrapper = el.find('.months');

      // Previous Month
      var prevMonth = parseInt(options.currentMonth) - 1,
        prevYear = parseInt(options.currentYear);

      if (prevMonth == 0) {
        prevMonth = 12;
        prevYear = prevYear - 1;
      }

      if (prevYear < options.startYear) {
        monthsWrapper.find('.prev-month').css('display', 'none');
      } else {
        if (prevMonth < options.startMonth && prevYear == options.startYear) {
          monthsWrapper.find('.prev-month').css('display', 'none');
        } else {
          monthsWrapper.find('.prev-month').css('display', '');
          monthsWrapper.find('.prev-month').attr('data-month', prevMonth);
          monthsWrapper.find('.prev-month').attr('data-year', prevYear);
        }
      }

      // Current Month
      monthsWrapper.find('.current-month').attr('data-month', options.currentMonth);
      monthsWrapper.find('.current-month').attr('data-year', options.currentYear);
      monthsWrapper.find('.current-month').html('<span class="month">' + (options.months[options.currentMonth - 1] || (options.currentMonth - 1)) + '</span><span class="year">' + options.currentYear + '</span>');

      // Next Month
      var nextMonth = parseInt(options.currentMonth) + 1,
        nextYear = parseInt(options.currentYear);

      if (nextMonth == 13) {
        nextMonth = 1;
        nextYear = nextYear + 1;
      }

      if (nextYear > options.endYear) {
        monthsWrapper.find('.next-month').css('display', 'none');
      } else {
        if (nextMonth > options.endMonth && nextYear == options.endYear) {
          monthsWrapper.find('.next-month').css('display', 'none');
        } else {
          monthsWrapper.find('.next-month').css('display', '');
          monthsWrapper.find('.next-month').attr('data-month', nextMonth);
          monthsWrapper.find('.next-month').attr('data-year', nextYear);
        }
      }

      el.find('.months a.change-month').off('click');

      getEvents(options.eventsUrl, options.currentMonth, options.currentYear, function() {
        el.find('.months a.change-month').one('click', function () {
          options.onMonthClick();

          var opts = {
            currentMonth: $(this).attr('data-month'),
            currentYear: $(this).attr('data-year')
          };
          opts = $.extend({}, options, opts);

          el.eventCalendar(opts);
        });

        options.onAfterLoad();
      });
    };

    /* initialize days */
    this.initDays = function () {
      var today = new Date();
      var daysWrapper = el.find('.days');
      var dayCount = new Date(options.currentYear, options.currentMonth, 0).getDate();
      var calendar = '',
        dayIndex = 0,
        dayNumber = 1,
        firstDay,
        i = 0;

      if (options.firstDayOfWeek == 1) {
        // Monday is first day
        firstDay = new Date(options.currentYear, options.currentMonth - 1, 1).getDay();
      } else {
        // Sunday is first day
        firstDay = new Date(options.currentYear, options.currentMonth - 1, 2).getDay();
      }

      for (i = options.firstDayOfWeek; i < (7 + options.firstDayOfWeek); i++) {
        dayIndex = i % 7;
        calendar += '<span class="day name">' + (options.days[dayIndex] || '&nbsp;') + '</span>';
      }

      if (firstDay == 0) {
        firstDay = 6;
      }

      var setToday;

      for (i = options.firstDayOfWeek; i < dayCount + firstDay; i++) {
        dayIndex = (i % 6);
        setToday = false;

        if (i < firstDay) {
          calendar += '<span class="day">&nbsp;</span>';
        } else {
          if ((options.currentYear == today.getFullYear())
            && ((options.currentMonth - 1) == today.getMonth())
            && (dayNumber == today.getDate())) {
            setToday = true;
          }

          calendar += '<span class="day' + (setToday ? ' today' : '') + '" data-day="' + dayNumber + '">' + dayNumber + '</span>';
          dayNumber++;
        }
      }

      daysWrapper.html(calendar);
    };

    /* CLICK on event day */
    el.on('click', '.day-active', function () {
      var e = $(this),
        day = e.attr('data-day');

      if (!e.hasClass('selected')) {
        options.onDayClick();

        $('.day.selected').removeClass('selected');
        e.addClass('selected');

        showEvent(day);
      }
    });

    function showEvent(day) {
      var target = $(options.eventsContainer);
      var currentEvent;

      options.events.forEach(function(event) {
        if (event.day == day) {
          currentEvent = event;
        }
      });

      if (currentEvent) {
        el.find('.day.selected').removeClass('selected');
        el.find('.day[data-day="' + currentEvent.day + '"]').addClass('selected');

        target.empty().html(
          '<h2>' + currentEvent.title + '</h2>' +
          '<h3>' + currentEvent.description + '</h3>' +
          '<a class="more" href="' + currentEvent.url + '">' + options.label.more + '</a>'
        );

        if (currentEvent.poster) {
          $('#event-poster').css({
            'background-image': 'url(' + currentEvent.poster + ')'
          });
        }
      }
    }

    /* SELECT active event days of current month from db */
    function getEvents(url, currentMonth, currentYear, callback) {
      options.events = [];
      $('#event-poster').css('background-image', '');
      $(options.eventsContainer).empty().removeClass('error').addClass('loading');

      $.ajax({
        url: url,
        type: 'GET',
        data: {
          month: currentMonth,
          year: currentYear
        },
        global: false
      }).done(function (result) {
        if (result && $.isArray(result) && result.length) {
          var days = [];

          result.forEach(function (event) {
            days.push(event.day);
          });

          options.events = result;

          el.find('.days > .day[data-day]').each(function () {
            $(this).removeClass('active');

            if (days.indexOf($(this).data('day')) > -1) {
              $(this).addClass('active');
            }
          });

          //Show first event
          showEvent(days[0]);
        } else {
          $(options.eventsContainer).addClass('error').html('<p>' + options.message.empty + '</p>');
        }

        $(options.eventsContainer).removeClass('loading');

        if (callback) {
          callback(result);
        }
      }).fail(function () {
        $(options.eventsContainer).addClass('error').html('<p>' + options.message.error + '</p>');
        $(options.eventsContainer).removeClass('loading');
      });
    }

    this.init();
  };
})(jQuery);