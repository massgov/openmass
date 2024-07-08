(function ($) {
  'use strict';

  $.fn.snoozed = function (data, refresh) {
    var main_id = data + '_row';
    var main_row = document.getElementById(main_id);
    main_row.classList.toggle('row-hidden');

    var confirm_id = data + '_row--snooze';
    var confirm_row = document.getElementById(confirm_id);
    confirm_row.classList.toggle('row-hidden');

    var show_more = document.getElementById('refresh-table').parentElement;
    if (refresh === true && show_more.classList.contains('refresh-hidden')) {
      show_more.classList.toggle('refresh-hidden');
    }
  };

  $.fn.snoozedUndo = function (data) {
    var main_id = data + '_row';
    var main_row = document.getElementById(main_id);
    main_row.classList.toggle('row-hidden');

    var confirm_id = data + '_row--snooze';
    var confirm_row = document.getElementById(confirm_id);
    confirm_row.classList.toggle('row-hidden');
  };

  $('summary.snooze-close').click(function () {
    var confirm_id = this.id + '_row--snooze';
    var confirm_row = document.getElementById(confirm_id);
    confirm_row.classList.toggle('row-hidden');
  });

  $.fn.reloadPage = function () {
    location.reload();
  };

})(jQuery);
