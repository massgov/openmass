(function ($, Drupal) {
  "use strict";

  /**
   * Add overlay when toolbar tray is active
   */
  Drupal.behaviors.toolbarTrayOverlay = {
    attach: function (context, settings) {
      // Only run once on document
      if (context !== document) {
        return;
      }

      // Create overlay element
      const overlay = $('<div class="toolbar-tray-overlay"></div>');

      if (!$(".toolbar-tray-overlay").length) {
        $("body").append(overlay);
      }

      // Function to show overlay and prevent scrolling
      function showOverlay() {
        $(".toolbar-tray-overlay").addClass("active");
        $("body").addClass("toolbar-tray-overlay-active");
      }

      // Function to hide overlay and restore scrolling
      function hideOverlay() {
        $(".toolbar-tray-overlay").removeClass("active");
        $("body").removeClass("toolbar-tray-overlay-active");
      }

      // Function to close the toolbar tray
      function closeToolbarTray() {
        // Manually removing active classes interferes with Drupal's toolbar toggle mechanism
        // Find the active toolbar tab and simulate a click to close it
        const activeTab = $(".toolbar-bar .toolbar-tab.is-active .toolbar-item");
                
        if (activeTab.length > 0) {
          activeTab.trigger("click");
        } else {
          // Fallback: try to use Drupal's toolbar model if available
          if (Drupal.toolbar && Drupal.toolbar.models && Drupal.toolbar.models.toolbarModel) {
            Drupal.toolbar.models.toolbarModel.set('activeTab', null);
          }
        }
        
        hideOverlay();
      }

      // Check toolbar state based on body class
      function checkToolbarState() {
        const bodyHasTrayOpen = $("body").hasClass("toolbar-tray-open");

        if (bodyHasTrayOpen) {
          showOverlay();
        } else {
          hideOverlay();
        }
      }

      // Handle clicks on navigation links within the toolbar tray
      // When user navigates to a different page, the toolbar tray should close automatically
      document.addEventListener('click', function(e) {
        // Check if click is inside toolbar tray
        const toolbarTray = e.target.closest('.toolbar-tray');
        if (!toolbarTray) return;
        
        // Check if click is on a link
        const link = e.target.closest('a');
        if (!link) return;
        
        const systemPath = link.getAttribute('data-drupal-link-system-path');
        const role = link.getAttribute('role');
        
        // Check if the link is an actual nav link
        if (role !== "button" && systemPath && systemPath.trim() !== "") {    
          closeToolbarTray();
        }
      }, true);

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
        checkToolbarState();
      }, 500);

    },
  };
})(jQuery, Drupal);
