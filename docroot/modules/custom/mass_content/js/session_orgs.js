(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Helper function to calculate the hours difference.
   */
  function diff_hours(dt2, dt1) {
    return Math.floor(Math.abs(dt2.getTime() - dt1.getTime()) / 36e5);
  }

  /**
   * Sets session_orgs and session_parent_orgs to storage based on the dataLayer.entityField_organizations and mg_parent_org values.
   */
  Drupal.behaviors.massContentOrgStore = {
    attach: function (context) {
      if (typeof window.dataLayer !== 'undefined' && typeof window.dataLayer[0].entityBundle !== 'undefined') {
        var dataLayer = window.dataLayer[0];
        if (dataLayer.entityBundle !== 'topic_page') {

          // Get related organizations from the dataLayer object.
          var orgsFiltered = getFilteredOrgString(dataLayer, 'entityField_organizations');
          var parentOrgsFiltered = getFilteredOrgString(dataLayer, 'entityField_org_parent');

          // Set Session start time for each user.
          var sessionStart = sessionStorage.getItem('session_start');
          // Reset session_start, session_orgs and session_parent_orgs from storage at sessionStart or if the hours difference is more than 1 hour.
          if (!sessionStart || sessionStart.length <= 0 || diff_hours(new Date(), new Date(sessionStart)) >= 1) {
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

  /**
   * Get a filtered list of slug:nid orgs.
   * @param {object} dataLayer - The dataLayer object from the window.
   * @param {string} dataLayerKey - The key to grab from the dataLayer object.
   *
   * @return {string[]} A list of orgs formatted as slug:nid
   */
  function getFilteredOrgString(dataLayer, dataLayerKey) {
    // Get related organizations from the dataLayer object.
    var dataLayerValue = dataLayer[dataLayerKey];
    var filtered = [];
    if (typeof dataLayerValue !== 'undefined') {
      $.each(dataLayerValue, function (nid, orgAttributes) {
        var orgNodeString = orgAttributes['slug'] + ':' + nid;
        if ($.inArray(orgNodeString, filtered) === -1) {
          filtered.push(orgNodeString);
        }
      });
    }
    return filtered;
  }

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
