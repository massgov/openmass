(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Helper function to calculate the hours difference.
   */
  function diff_hours(dt2, dt1) {
    return Math.floor(Math.abs(dt2.getTime() - dt1.getTime()) / 36e5);
  }

  /**
   * Sets session_orgs and session_parent_orgs to storage based on the mg_organization and mg_parent_org values.
   */
  Drupal.behaviors.massContentOrgStore = {
    attach: function (context) {
      if (typeof window.dataLayer !== 'undefined' && typeof window.dataLayer[0].entityBundle !== 'undefined') {
        if (window.dataLayer[0].entityBundle !== 'topic_page') {
          var orgsFiltered = [];
          var parentOrgsFiltered = [];
          var orgs = '';
          var parentOrgs = '';

          if ($("meta[name='mg_organization']").length > 0) {
            // Get data from the metatag element.
            orgs = $("meta[name='mg_organization']").attr('content');
            // Filter the array and keep only unique values.
            var orgsArr = orgs.split(',');
            $.each(orgsArr, function (i, el) {
              if ($.inArray(el, orgsFiltered) === -1) {
                orgsFiltered.push(el);
              }
            });
          }

          if ($("meta[name='mg_parent_org']").length > 0) {
            // Get data from the metatag element.
            parentOrgs = $("meta[name='mg_parent_org']").attr('content');
            // Filter the array and keep only unique values.
            var parentOrgsArr = parentOrgs.split(',');
            $.each(parentOrgsArr, function (i, el) {
              if ($.inArray(el, parentOrgsFiltered) === -1) {
                parentOrgsFiltered.push(el);
              }
            });
          }

          // Set Session start time for each user.
          var sessionStart = sessionStorage.getItem('session_start');
          // Reset session_start, session_orgs and session_parent_orgs from storage at sessionStart or if the hours difference is more than 1 hour.
          if (!sessionStart || sessionStart.length <= 0 ||  diff_hours(new Date(), new Date(sessionStart)) >= 1) {
            sessionStorage.setItem('session_start', new Date().toString());
            if (orgsFiltered.length > 0) {
              sessionStorage.setItem('session_orgs', orgsFiltered.join(','));
            }
            if (parentOrgsFiltered.length > 0) {
              sessionStorage.setItem('session_parent_orgs', parentOrgsFiltered.join(','));
            }
          }
          updateSessionStorage('session_orgs', orgsFiltered);
          updateSessionStorage('session_parent_orgs', parentOrgsFiltered);
        }
      }
    }
  };

  // Set session storage to passed arrays with unique values.
  function updateSessionStorage(key, valuesFiltered) {
    var existing = sessionStorage.getItem(key);
    if (existing && existing.length > 0) {
      // Filter the array and keep only unique values.
      var existingValues = existing.split(',');
      if (valuesFiltered.length > 0) {
        var resultValues = existingValues.concat(valuesFiltered);
        var resultFiltered = [];
        $.each(resultValues, function (i, el) {
          if ($.inArray(el, resultFiltered) === -1) {
            resultFiltered.push(el);
          }
        });
        sessionStorage.setItem(key, resultFiltered.join(','));
      }
    }
    else {
      if (valuesFiltered.length > 0) {
        sessionStorage.setItem(key, valuesFiltered.join(','));
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
