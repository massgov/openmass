(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Helper function to calculate the hours difference.
   */
  function diff_hours(dt2, dt1) {
    // 36e5 is the scientific notation for 60*60*1000
    return Math.floor(Math.abs(dt2.getTime() - dt1.getTime()) / 36e5);
  }

  /**
   * Sets session_orgs to storage based on the mg_organization value.
   */
  Drupal.behaviors.massContentOrgStore = {
    attach: function (context) {
      // window.dataLayer[0].entityBundle stores content_type label.
      if (typeof window.dataLayer !== 'undefined' && typeof window.dataLayer[0].entityBundle !== 'undefined') {
        if (window.dataLayer[0].entityBundle !== 'topic_page') {
          var orgsFiltered = [];
          var orgs = '';
          if ($("meta[name='mg_organization']").length > 0) {
            // Get data from the metatag element.
            orgs = $("meta[name='mg_organization']").attr('content');
            var orgsArr = orgs.split(',');
            // Filter the array and keep only unique values.
            $.each(orgsArr, function (i, el) {
              if ($.inArray(el, orgsFiltered) === -1) {
                orgsFiltered.push(el);
              }
            });
          }
          // Set Session start time for each user.
          var sessionStart = sessionStorage.getItem('session_start');
          if (sessionStart && sessionStart.length > 0) {
            // Remove session_orgs from storage only if the hours difference
            // is more than 1 hour.
            if (diff_hours(new Date(), new Date(sessionStart)) >= 1) {
              sessionStorage.setItem('session_start', new Date().toString());
              var sessionOrgs = sessionStorage.getItem('session_orgs');
              if (sessionOrgs && sessionOrgs.length > 0) {
                if (orgsFiltered.length > 0) {
                  // Set session_orgs value to storage.
                  sessionStorage.setItem('session_orgs', orgsFiltered.join(','));
                }
              }
            }
          }
          else {
            // Set session_start value to storage.
            sessionStorage.setItem('session_start', new Date().toString());
          }

          var sessionOrgsExisting = sessionStorage.getItem('session_orgs');
          if (sessionOrgsExisting && sessionOrgsExisting.length > 0) {
            // Convert string to array to filter for unique values.
            var existingValues = sessionOrgsExisting.split(',');
            if (orgsFiltered.length > 0) {
              // Combine existing and new data together in 1 array.
              var resultValues = existingValues.concat(orgsFiltered);
              var resultFiltered = [];
              // Filter the array and keep only unique values.
              $.each(resultValues, function (i, el) {
                if ($.inArray(el, resultFiltered) === -1) {
                  resultFiltered.push(el);
                }
              });
              // Set session_orgs value to storage.
              sessionStorage.setItem('session_orgs', resultFiltered.join(','));
            }
          }
          else {
            if (orgsFiltered.length > 0) {
              // Set session_orgs value to storage.
              sessionStorage.setItem('session_orgs', orgsFiltered.join(','));
            }
          }
        }
      }

    }
  };
})(jQuery, Drupal, drupalSettings);
