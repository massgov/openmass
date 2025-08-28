(function ($, Drupal, window) {
  "use strict";

  /**
   * Check if browser zoom is above 150%
   */
  function isZoomAbove150() {
    const zoomLevel = Math.round((window.outerWidth / window.innerWidth) * 100);
    console.log(zoomLevel);
    return zoomLevel >= 150;
  }

  /**
   * Force horizontal toolbar orientation
   */
  function forceHorizontalToolbar() {
    console.log("Forcing horizontal toolbar");

    // Method 1: Direct CSS override
    $("body").addClass("toolbar-zoom-override");

    // Method 2: Try to set model if available
    if (
      Drupal.toolbar &&
      Drupal.toolbar.models &&
      Drupal.toolbar.models.toolbarModel
    ) {
      const toolbarModel = Drupal.toolbar.models.toolbarModel;
      toolbarModel.set({
        orientation: "horizontal",
        locked: false,
        isTrayToggleVisible: false,
      });
    }
  }

  /**
   * Override the toolbar orientation behavior for high zoom levels
   */
  Drupal.behaviors.toolbarZoomOverride = {
    attach: function (context, settings) {
      console.log("Toolbar zoom override behavior attached");

      // Add CSS class for high zoom
      if (isZoomAbove150()) {
        forceHorizontalToolbar();
      }

      // Listen for zoom changes
      let resizeTimer;
      $(window).on("resize.toolbarZoom", function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
          if (isZoomAbove150()) {
            forceHorizontalToolbar();
          } else {
            $("body").removeClass("toolbar-zoom-override");
          }
        }, 250);
      });

      // Override toolbar model behavior if it exists
      $(document).ready(function () {
        setTimeout(function () {
          if (
            Drupal.toolbar &&
            Drupal.toolbar.models &&
            Drupal.toolbar.models.toolbarModel
          ) {
            const toolbarModel = Drupal.toolbar.models.toolbarModel;

            // Store original method
            if (typeof toolbarModel.onMediaQueryChange === "function") {
              toolbarModel.originalOnMediaQueryChange =
                toolbarModel.onMediaQueryChange;
            }

            // Override the media query change method
            toolbarModel.onMediaQueryChange = function () {
              console.log("Media query change detected");

              if (isZoomAbove150()) {
                console.log("High zoom detected, forcing horizontal");
                this.set({
                  orientation: "horizontal",
                  locked: false,
                  isTrayToggleVisible: false,
                });
                return;
              }

              // Otherwise, use the original behavior
              if (this.originalOnMediaQueryChange) {
                this.originalOnMediaQueryChange.apply(this, arguments);
              }
            };

            // Initial check
            if (isZoomAbove150()) {
              forceHorizontalToolbar();
            }
          }
        }, 1000); // Give toolbar time to initialize
      });
    },
  };
})(jQuery, Drupal, window);
