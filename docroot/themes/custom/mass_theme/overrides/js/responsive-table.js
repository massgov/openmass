/**
 * @file
 * Support responsive tables in rte
 */

(function ($) {
  "use strict";

  document.querySelectorAll(".ma__table--responsive .ma__table").forEach(function (table) {
    // Copy captions authors entered into Mayflower template format.
    // table-responsive.twig L.12
    if ($(table).find("caption:not(.ma__table__caption)")) {
      var userInput = $(table).find("caption:not(.ma__table__caption)");
      var captionText = $(userInput).text();
      var captionSnippet =
        '<span class="ma__table__caption__content">' + captionText + "</span>";
      $(table).find(".ma__table__caption").prepend(captionSnippet);
      $(userInput).remove();
    }

    // Check table cell count for .ma__table--wide.
    // table-responsive.twig L.4
    if ($(table).find("th").length > 3) {
      $(table).addClass("ma__table--wide");
    }
  });

})(jQuery);
