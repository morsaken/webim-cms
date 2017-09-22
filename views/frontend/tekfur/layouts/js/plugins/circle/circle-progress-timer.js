function CircleTimer(config, cp_config) {
  this.init(config, cp_config);
}

CircleTimer.prototype = {
  
  config: null,
  cp_config: null,

  cp_config_defaults: {
    startAngle: 1.5 * Math.PI,
    value: 0
  },
  
  duration: 60,
  progressInterval: null,
  $target: null,
  timer: null,

  init: function(config, cp_config) {
    this.config = config;
    this.$target = $(config["target"]);

    if ( this.$target.size() == 0 ) { return false; }

    // determine how much we need to move the progress meter
    // every time depending on the duration needed
    this.duration = config["duration"] || 60;
    this.progressInterval = parseFloat(100 / this.duration / 100).toFixed(3);

    this.cp_config = $.extend({}, this.cp_config_defaults, cp_config);

    // setup the circle progress on the target
    this.$target.circleProgress(this.cp_config);

    this.timer = this.$target.data("circleProgress");
  },

  start: function() {
    // set local variables because the setInterval function
    // will execute in the scope of this function
    var timer = this.timer;
    var $target = this.$target;
    var onFinished = this.config["onFinished"];
    var progressInterval = parseFloat(this.progressInterval);

    // start the timer
    var timerIntervalId = setInterval(function() {
      // calculate the next progress step
      timer.value = parseFloat(parseFloat(timer.value + progressInterval).toFixed(3));

      // check if the progress is complete (hitting 1 or more means done)
      if ( timer.value >= 1 ) {
        timer.value = 1; // set it to 1 so it looks perfectly complete
        clearInterval(timerIntervalId); // stop the timer
        
        // run the onFinished callback if set
        if (typeof(onFinished) == "function") {
          onFinished($target);
        }
      }

      // redraw the circle with the new value
      timer.draw();
    }, 1000);

    // set the timer interval on the elements data incase someone else
    // wants to stop the timer
    $target.data('timerIntervalId', timerIntervalId);
  },

  stop: function() {
    clearInterval(this.$target.data('timerIntervalId')); // stop the timer
  }

}