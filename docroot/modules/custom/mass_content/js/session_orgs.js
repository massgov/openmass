(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Helper function to calculate the hours difference.
   */
  function diff_hours(dt2, dt1) {
    let diff = (dt2.getTime() - dt1.getTime()) / 1000;
    diff /= (60 * 60);
    return Math.abs(Math.round(diff));
  }

  /**
   * Sets session_orgs to storage based on the mg_organization value.
   */
  Drupal.behaviors.massContentOrgStore = {
    attach: function (context) {

      // Get data from the metatag element.
      let orgs = $("meta[name='mg_organization']").attr('content');
      let orgsArr = orgs.split(',')
      let orgsFiltered = [];
      // Filter the array and keep only unique values.
      $.each(orgsArr, function(i, el){
        if($.inArray(el, orgsFiltered) === -1) orgsFiltered.push(el);
      });
      // Set Session start time for each user.
      let sessionStart = sessionStorage.getItem('session_start');
      if (sessionStart && sessionStart.length > 0) {
        // Remove session_orgs from storage only if the hours difference
        // is more than 1 hour.
        if (diff_hours(new Date(), new Date(sessionStart)) >= 1) {
          sessionStorage.setItem('session_start', new Date().toString());
          let sessionOrgs = sessionStorage.getItem('session_orgs');
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

      let sessionOrgs = sessionStorage.getItem('session_orgs');
      if (sessionOrgs && sessionOrgs.length > 0) {
        // Convert string to array to filter for unique values.
        let existingValues = sessionOrgs.split(',')
        if (orgsFiltered.length > 0) {
          // Combine existing and new data together in 1 array.
          let resultValues = existingValues.concat(orgsFiltered);
          let resultFiltered = [];
          // Filter the array and keep only unique values.
          $.each(resultValues, function(i, el){
            if($.inArray(el, resultFiltered) === -1) resultFiltered.push(el);
          });
          // Set session_orgs value to storage.
          sessionStorage.setItem('session_orgs', resultFiltered.join(','));
        }
      }
      else {
        if (typeof orgs === 'string') {
          if (orgsFiltered.length > 0) {
            // Set session_orgs value to storage.
            sessionStorage.setItem('session_orgs', orgsFiltered.join(','));
          }
        }
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
