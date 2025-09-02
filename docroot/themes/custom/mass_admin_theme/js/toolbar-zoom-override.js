(function ($, Drupal) {
  "use strict";

  console.log("Toolbar overlay script loaded");

  /**
   * Add overlay when toolbar tray is active
   */
  Drupal.behaviors.toolbarTrayOverlay = {
    attach: function (context, settings) {
      console.log("Toolbar overlay behavior attached");

      // Only run once on document
      if (context !== document) {
        return;
      }

      // Create overlay element
      const overlay = $('<div class="toolbar-tray-overlay"></div>');

      // Add overlay to body (only once)
      if (!$(".toolbar-tray-overlay").length) {
        $("body").append(overlay);
        console.log("Overlay element added to body");
      }

      // Function to show overlay and prevent scrolling
      function showOverlay() {
        console.log("showOverlay called");
        $(".toolbar-tray-overlay").addClass("active");
        $("body").addClass("toolbar-tray-overlay-active");
        console.log("Overlay shown");
      }

      // Function to hide overlay and restore scrolling
      function hideOverlay() {
        console.log("hideOverlay called");
        $(".toolbar-tray-overlay").removeClass("active");
        $("body").removeClass("toolbar-tray-overlay-active");
        console.log("Overlay hidden");
      }

      // Function to close the toolbar tray
      function closeToolbarTray() {
        console.log("closeToolbarTray called");

        // Remove is-active class from toolbar items and trays
        const activeToolbarItems = $(".toolbar-item.is-active");
        const activeTrays = $(".toolbar-tray.is-active");

        console.log("Active toolbar items found:", activeToolbarItems.length);
        console.log("Active trays found:", activeTrays.length);

        if (activeToolbarItems.length > 0) {
          console.log("Removing is-active from toolbar items");
          activeToolbarItems.removeClass("is-active");
        }

        if (activeTrays.length > 0) {
          console.log("Removing is-active from toolbar trays");
          activeTrays.removeClass("is-active");
        }

        // Also remove toolbar-tray-open from body if it exists
        if ($("body").hasClass("toolbar-tray-open")) {
          console.log("Removing toolbar-tray-open from body");
          $("body").removeClass("toolbar-tray-open");
        }

        hideOverlay();
      }

      // Check toolbar state
      function checkToolbarState() {
        const bodyHasTrayOpen = $("body").hasClass("toolbar-tray-open");
        const activeTrays = $(".toolbar-tray.is-active");
        const activeItems = $(".toolbar-item.is-active");

        console.log("=== Toolbar State Check ===");
        console.log("Body has toolbar-tray-open:", bodyHasTrayOpen);
        console.log("Active trays:", activeTrays.length);
        console.log("Active items:", activeItems.length);

        if (
          bodyHasTrayOpen ||
          activeTrays.length > 0 ||
          activeItems.length > 0
        ) {
          console.log("Tray is open - showing overlay");
          showOverlay();
        } else {
          console.log("Tray is closed - hiding overlay");
          hideOverlay();
        }
      }

      // Watch for clicks on toolbar items
      $(document).on("click", ".toolbar-item", function (e) {
        console.log("Toolbar item clicked:", $(this).text());

        // Small delay to let Drupal process the click and update classes
        setTimeout(function () {
          checkToolbarState();
        }, 100);
      });

      // Handle overlay click to close tray
      $(document).on("click", ".toolbar-tray-overlay.active", function (e) {
        console.log("Overlay clicked");
        e.preventDefault();
        e.stopPropagation();
        closeToolbarTray();
      });

      // Initial check on page load
      setTimeout(function () {
        console.log("Initial toolbar state check...");
        checkToolbarState();
      }, 500);

      console.log("Toolbar tray overlay behavior setup complete");
    },
  };
})(jQuery, Drupal);
