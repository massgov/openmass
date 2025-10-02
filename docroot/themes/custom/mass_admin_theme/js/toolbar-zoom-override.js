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
        
        // Instead of manually removing classes, try to trigger Drupal's close mechanism
        // Find the active toolbar tab and simulate a click to close it
        const activeTab = $(".toolbar-bar .toolbar-tab.is-active .toolbar-item");
        
        console.log("Active toolbar tabs found:", activeTab.length);
        
        if (activeTab.length > 0) {
          console.log("Clicking active tab to close toolbar");
          activeTab.trigger("click");
        } else {
          // Fallback: try to use Drupal's toolbar model if available
          if (Drupal.toolbar && Drupal.toolbar.models && Drupal.toolbar.models.toolbarModel) {
            console.log("Using Drupal toolbar model to close");
            Drupal.toolbar.models.toolbarModel.set('activeTab', null);
          } else {
            // Last resort: manually remove classes
            console.log("Fallback: manually removing classes");
            const activeToolbarItems = $(".toolbar-item.is-active");
            const activeTrays = $(".toolbar-tray.is-active");
            
            if (activeToolbarItems.length > 0) {
              activeToolbarItems.removeClass("is-active");
            }
            
            if (activeTrays.length > 0) {
              activeTrays.removeClass("is-active");
            }
            
            if ($("body").hasClass("toolbar-tray-open")) {
              $("body").removeClass("toolbar-tray-open");
            }
          }
        }
        
        hideOverlay();
      }

      // Check toolbar state based on body class
      function checkToolbarState() {
        const bodyHasTrayOpen = $("body").hasClass("toolbar-tray-open");

        console.log("=== Toolbar State Check ===");
        console.log("Body has toolbar-tray-open:", bodyHasTrayOpen);

        if (bodyHasTrayOpen) {
          console.log("Tray is open - showing overlay");
          showOverlay();
        } else {
          console.log("Tray is closed - hiding overlay");
          hideOverlay();
        }
      }

      // Handle clicks on navigation links within the toolbar tray
      $(document).on("click", ".toolbar-tray a", function (e) {
        const $link = $(this);
        const systemPath = $link.attr("data-drupal-link-system-path");
        const role = $link.attr("role");
        
        console.log("=== LINK CLICKED IN TOOLBAR ===");
        console.log("Link text:", $link.text().trim());
        
        if (role !== "button" && systemPath && systemPath.trim() !== "") {
          console.log("Closing tray - navigation link detected");
          
          // Prevent the mutation observer from interfering
          e.stopImmediatePropagation();
          
          // Close the tray immediately
          closeToolbarTray();
          
          // Let the navigation proceed normally
          return true;
        }
      });

      // Handle overlay click to close tray
      $(document).on("click", ".toolbar-tray-overlay.active", function (e) {
        console.log("Overlay clicked");
        e.preventDefault();
        e.stopPropagation();
        closeToolbarTray();
      });

      // Monitor body class changes using MutationObserver (AFTER click handlers)
      const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          if (mutation.type === "attributes" && mutation.attributeName === "class") {
            console.log("Body class changed, checking toolbar state");
            checkToolbarState();
          }
        });
      });

      // Start observing body class changes
      observer.observe(document.body, {
        attributes: true,
        attributeFilter: ["class"],
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
