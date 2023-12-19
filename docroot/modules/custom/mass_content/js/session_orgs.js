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
            orgs = $("meta[name='mg_organization']").attr('content');
            var orgsArr = orgs.split(',');
            $.each(orgsArr, function (i, el) {
              if ($.inArray(el, orgsFiltered) === -1) {
                orgsFiltered.push(el);
              }
            });
          }

          if ($("meta[name='mg_parent_org']").length > 0) {
            parentOrgs = $("meta[name='mg_parent_org']").attr('content');
            var parentOrgsArr = parentOrgs.split(',');
            $.each(parentOrgsArr, function (i, el) {
              if ($.inArray(el, parentOrgsFiltered) === -1) {
                parentOrgsFiltered.push(el);
              }
            });
          }

          var sessionStart = sessionStorage.getItem('session_start');
          if (sessionStart && sessionStart.length > 0) {
            if (diff_hours(new Date(), new Date(sessionStart)) >= 1) {
              sessionStorage.setItem('session_start', new Date().toString());
              if (orgsFiltered.length > 0) {
                sessionStorage.setItem('session_orgs', orgsFiltered.join(','));
              }
              if (parentOrgsFiltered.length > 0) {
                sessionStorage.setItem('session_parent_orgs', parentOrgsFiltered.join(','));
              }
            }
          } else {
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

  function updateSessionStorage(key, valuesFiltered) {
    var existing = sessionStorage.getItem(key);
    if (existing && existing.length > 0) {
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
    } else {
      if (valuesFiltered.length > 0) {
        sessionStorage.setItem(key, valuesFiltered.join(','));
      }
    }
  }

})(jQuery, Drupal, drupalSettings);
