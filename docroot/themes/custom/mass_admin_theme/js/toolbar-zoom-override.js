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
        // Find the active tab and click it to close
        const activeTab = $(
          ".toolbar-bar .toolbar-tab.is-active .toolbar-item, .toolbar-bar .toolbar-tab.open .toolbar-item"
        );
        console.log("Active tabs found:", activeTab.length);
        if (activeTab.length > 0) {
          activeTab.trigger("click");
        }
        hideOverlay();
      }

      // Check toolbar state - improved detection
      function checkToolbarState() {
        // Multiple ways to detect if toolbar tray is open
        const bodyHasTrayOpen = $("body").hasClass("toolbar-tray-open");
        const activeTrays = $(".toolbar-tray.is-active, .toolbar-tray:visible");
        const activeTabs = $(".toolbar-tab.is-active, .toolbar-tab.open");

        console.log("=== Toolbar State Check ===");
        console.log("Body has toolbar-tray-open:", bodyHasTrayOpen);
        console.log("Active trays:", activeTrays.length);
        console.log("Active tabs:", activeTabs.length);
        console.log("Body classes:", $("body").attr("class"));

        // If body has toolbar-tray-open class, tray is definitely open
        if (
          bodyHasTrayOpen ||
          (activeTrays.length > 0 && activeTabs.length > 0)
        ) {
          console.log("Tray is open - showing overlay");
          showOverlay();
          return true;
        } else {
          console.log("Tray is closed - hiding overlay");
          hideOverlay();
          return false;
        }
      }

      // Watch for toolbar tab clicks
      $(document).on(
        "click",
        ".toolbar-bar .toolbar-tab .toolbar-item",
        function (e) {
          console.log("Toolbar tab clicked:", $(this).text());

          // Small delay to let Drupal process the click
          setTimeout(function () {
            checkToolbarState();
          }, 200);
        }
      );

      // Handle overlay click to close tray
      $(document).on("click", ".toolbar-tray-overlay.active", function (e) {
        console.log("Overlay clicked");
        e.preventDefault();
        e.stopPropagation();
        closeToolbarTray();
      });

      // Watch for body class changes using MutationObserver
      const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          if (
            mutation.type === "attributes" &&
            mutation.attributeName === "class"
          ) {
            const bodyClasses = $("body").attr("class");
            console.log("Body class changed:", bodyClasses);

            // Check specifically for toolbar-tray-open class
            if (bodyClasses.includes("toolbar-tray-open")) {
              console.log("toolbar-tray-open detected - showing overlay");
              showOverlay();
            } else {
              console.log("toolbar-tray-open not detected - hiding overlay");
              hideOverlay();
            }
          }
        });
      });

      observer.observe(document.body, {
        attributes: true,
        attributeFilter: ["class"],
      });

      // Initial check
      setTimeout(function () {
        console.log("Initial toolbar state check...");
        checkToolbarState();
      }, 1000);

      console.log("Toolbar tray overlay behavior setup complete");
    },
  };
})(jQuery, Drupal);
