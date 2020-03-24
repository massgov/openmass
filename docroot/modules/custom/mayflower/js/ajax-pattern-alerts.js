/**
 * @file
 * Functions for serializing alerts jsonapi response to json accepted by mayflower ajax pattern.
 *
 * Fields used in processing (formatted as query string):
 *  * fields[node--alert]=title,changed,entity_url,field_alert_severity,field_alert,field_target_pages_para_ref,field_alert_display
 *  * fields[paragraph--emergency_alert]=drupal_internal__id,changed,field_emergency_alert_timestamp,field_emergency_alert_message,field_emergency_alert_link,field_emergency_alert_content
 *  * fields[paragraph--target_pages]=field_target_content_ref
 *  * include=field_target_pages_para_ref,field_alert
 *  * filter[status][value]=1
 */

(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.mayflower = {

    getSerializedSiteAlertData: function getSerializedSiteAlertData(responseData) {
      // Start with the scaffolding for serialized site alert data
      // to make life easy and awesome. Like no checks for if a propertyName exists.
      var serializedSiteAlertData = {
        emergencyAlerts: {
          id: null,
          buttonAlert: {
            hideText: 'Hide',
            showText: 'Show',
            text: 'Alerts'
          },
          emergencyHeader: {
            title: null
          },
          alerts: []
        }
      };

      responseData.data.forEach(function (item) {
        // Don't process if we don't have an alert content
        if (item.type !== 'node--alert') {
          return;
        }
        // See if alert is site wide
        if (item.attributes.field_alert_display === 'site_wide') {
          // Create unique id by combining uuid and changed timestamp.
          // Because mayflower wants this id to change everytime alert changes.
          var id = item.id + '__' + item.attributes.changed;
          serializedSiteAlertData.emergencyAlerts.id = id;

          serializedSiteAlertData.emergencyAlerts.emergencyHeader.title = item.attributes.title;
          if (item.attributes.field_alert_severity && item.attributes.field_alert_severity === 'informational_notice') {
            serializedSiteAlertData.emergencyAlerts.emergencyHeader.prefix = 'Informational Alert';
          }
          var currentAlertItem = item;
          var alertParagraphIds = Drupal.behaviors.mayflower.getAlertParagraphIds(item);
          var alertParagraphData = Drupal.behaviors.mayflower.getAlertParagraphData(responseData, alertParagraphIds, currentAlertItem);
          serializedSiteAlertData.emergencyAlerts.alerts = alertParagraphData;
        }
        // NOTE: Currently, if we have multiple site_wide alerts, the N+1th alert data will override Nth alert data.
        // This is by design because only 1 site_wide alert should be published at any given time.
        // Drupal enforces it partially and will fully enforce it after this fix - https://jira.state.ma.us/browse/DP-7095
        // After DP-7095 fix has been shipped, this note can be deleted, no other code change is requried.
      });
      // Don't return the scaffolding we started out with, return only
      // the legit serialized data.
      if (serializedSiteAlertData.emergencyAlerts.id === null) {
        return {};
      }
      return serializedSiteAlertData;
    },

    // Returns list of paragraph ids, that hold alert details, while ignores
    // other paragraph types like the ones that hold alert target pages, etc.
    getAlertParagraphIds: function getAlertParagraphIds(alertData) {
      var alertParagraphIds = [];
      try {
        alertData.relationships.field_alert.data.forEach(function (item) {
          if (item.type === 'paragraph--emergency_alert') {
            alertParagraphIds.push(item.id);
          }
        });
      }
      catch (e) {
        console.error(e);
      }
      return alertParagraphIds;
    },

    // Returns array of individual alert message data, in a consistent manner
    // for site wide and page based alerts.
    getAlertParagraphData: function getAlertParagraphData(responseData, alertParagraphIds, currentAlertItem) {
      var alertParagraphData = [];
      try {
        //
        responseData.included.forEach(function (item) {
          // NOTE: We have a polyfill to ensure Array.includes() works for us in all browsers.
          if (item.type === 'paragraph--emergency_alert' && alertParagraphIds.includes(item.id)) {
            // We generate a unique id, that changes everytime alert content is updated.
            var id = item.id + '__' + currentAlertItem.attributes.changed;
            // NOTE: Drupal stores timestamps in UTC and renders them in whatever timezone setting the site or user has set.
            // No rendering is involved when Drupal returns jsonapi data, so the timestamps are always in UTC.
            // So we read the desired timezone offset and adjust here, or fall back on EST offset if a configuarable offset is not exposed.
            var utfOffsetString = (drupalSettings.mayflower && drupalSettings.mayflower.utcOffsetString) ? drupalSettings.mayflower.utcOffsetString : '-05:00';
            var timeStamp = (typeof moment === 'function') ? moment.utc(item.attributes.field_emergency_alert_timestamp).utcOffset(utfOffsetString).format('MMM. Do, YYYY, h:mm a') : item.attributes.field_emergency_alert_timestamp;
            var serializedAlertParagraph = {
              id: id,
              message: item.attributes.field_emergency_alert_message,
              timeStamp: timeStamp
            };
            // Start with empty alert link for serialized alert data
            serializedAlertParagraph.link = {
              href: null,
              text: null
            };
            // If alert HAS a link, use it
            if (item.attributes.field_emergency_alert_link && item.attributes.field_emergency_alert_link.uri) {
              serializedAlertParagraph.link = {
                href: item.attributes.field_emergency_alert_link.uri,
                text: 'Read more',
                chevron: true
              };
            }
            // If alert has NO link, but has "body"
            // use "SITE.com/alerts#id" as link for site wide alerts
            // use "SITE.com/alert/[alert-clean-url]#id" as link for page specific alerts
            // NOTE: appending #id at the end allows us to link directly to
            // the anchor link of a particular alert message on the full page.
            else if (item.relationships.field_emergency_alert_content.data.length > 0) {
              if (currentAlertItem.attributes.field_alert_display === 'site_wide') {
                serializedAlertParagraph.link = {
                  href: '/alerts' + '#' + item.attributes.drupal_internal__id,
                  text: 'Read more',
                  chevron: true
                };
              }
              else if (currentAlertItem.attributes.field_alert_display === 'specific_target_pages') {
                serializedAlertParagraph.link = {
                  href: currentAlertItem.attributes.entity_url + '#' + item.attributes.drupal_internal__id,
                  text: 'Read more',
                  chevron: true
                };
              }
            }
            alertParagraphData.push(serializedAlertParagraph);
          }
        });
      }
      catch (e) {
        console.error(e);
      }
      return alertParagraphData;
    },


    getSerializedPageAlertData: function getSerializedPageAlertData(responseData) {
      var serializedPageAlertData = {headerAlerts: []};
      var paragraphsWithCurrentPageAsTarget = [];
      var currentPageUuid = null;

      // If we do not know current page's uuid, we will
      // not be able to target it, so, abort.
      try {
        currentPageUuid = window.dataLayer[0].entityUuid;
      }
      catch (e) {
        console.error(e);
      }
      if (!currentPageUuid) {
        return {};
      }

      try {
        // In the response, paragraph references are included separately from
        // the alerts. We collect any paragraphs references that hold
        // the current page as a target.
        // If the current page is in none of them, we abort.
        responseData.included.forEach(function (item) {
          if (item.type !== 'paragraph--target_pages') {
            return;
          }
          if (item.relationships.field_target_content_ref.data !== null) {
            if (item.relationships.field_target_content_ref.data.id === currentPageUuid) {
              paragraphsWithCurrentPageAsTarget.push(item.id);
            }
          }
        });
        if (paragraphsWithCurrentPageAsTarget.length === 0) {
          return {};
        }
      }
      catch (e) {
        console.error(e);
      }

      // Now we iterate on each alert data.
      responseData.data.forEach(function (item) {
        // Don't process if it not alert content
        if (item.type !== 'node--alert') {
          return;
        }
        // See if the alert item is for specific target pages.
        if (item.attributes.field_alert_display === 'specific_target_pages') {
          var currentAlertItem = item;
          // See if any paragraph in this alert is connected to current page
          // If yes, we want this alert.
          currentAlertItem.relationships.field_target_pages_para_ref.data.forEach(function (paraItem) {
            // NOTE: We have a polyfill to ensure Array.includes() works for us in all browsers.
            if (paragraphsWithCurrentPageAsTarget.includes(paraItem.id)) {
              var alertDetailParagraphIds = Drupal.behaviors.mayflower.getAlertParagraphIds(currentAlertItem);
              var alertDetailParagraphData = Drupal.behaviors.mayflower.getAlertParagraphData(responseData, alertDetailParagraphIds, currentAlertItem);
              alertDetailParagraphData.forEach(function (alertData) {
                // NOTE: getAlertParagraphData already sets the id such that it is unique and it changes everytime an alert content is udpated.
                // We use it to show an alert again, if a user had previously dismissed it, but if the alert now has new updated content.
                var serializedAlertItem = {
                  id: alertData.id,
                  text: alertData.message,
                  href: alertData.link.href,
                  info: ''
                };
                if (currentAlertItem.attributes.field_alert_severity && currentAlertItem.attributes.field_alert_severity === 'informational_notice') {
                  serializedAlertItem.prefix = 'Notice';
                }
                serializedPageAlertData.headerAlerts.push(serializedAlertItem);
              });
              // Exit the loop to prevent multiple copies.
              return;
            }
          });
        }
      });

      return serializedPageAlertData;
    },

    /**
     * Drupal behavior.
     *
     * @param {HTMLDocument|HTMLElement} context
     * The context argument for Drupal.attachBehaviors()/detachBehaviors().
     * @param {object} settings
     * The settings argument for Drupal.attachBehaviors()/detachBehaviors().
     */
    attach: function (context, settings) {
      // Note that this selector is passed into the template so that this is run instead of the
      // default 'js-ajax-pattern'.  See the ajaxPattern.customSelector in guide.json page object.
      // In an implementation of this you would want to create your own selector and avoid
      // js-ajax-pattern and js-ajax-pattern-override since Mayflower will attach to these.
      // See: https://stackoverflow.com/questions/18911182/passing-arguments-to-jquery-each-function
      var processAlerts = function (x) {
        var transformFunction = x;
        return function (index, element) {
          // Get the endpoint which is passed in as ajaxAlerts.endpoint to organism data attribute.
          var $self = $(this);
          var endpoint = $self.data('ma-ajax-endpoint');
          if (!endpoint) {
            console.error('MA::AjaxPattern::This pattern requires an endpoint to be passed in as an argument.');
            return false;
          }

          var renderPattern = $self.data('ma-ajax-render-pattern');
          if (!renderPattern) {
            console.error('MA::AjaxPattern::This pattern requires a child pattern to be passed as an argument.');
            return false;
          }
          try {
            $self.MassAjaxPattern({
              endpoint: endpoint,
              renderPattern: renderPattern,
              transform: transformFunction
            });
          }
          catch (e) {
            console.error(e);
          }
        };
      };

      $('.js-ajax-site-alerts-jsonapi', context).each(processAlerts(Drupal.behaviors.mayflower.getSerializedSiteAlertData));
      $('.js-ajax-page-alerts-jsonapi', context).each(processAlerts(Drupal.behaviors.mayflower.getSerializedPageAlertData));
    }

  };
})(jQuery, Drupal, drupalSettings);
