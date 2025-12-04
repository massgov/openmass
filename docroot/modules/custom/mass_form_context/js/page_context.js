(function (Drupal, drupalSettings, once) {
  'use strict';

  var STORAGE_KEY = 'massFormContext';
  var TTL_MS = 24 * 60 * 60 * 1000; // 24 hours

  function loadStorage() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return {forms: {}, lastPage: null};
      }
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') {
        return {forms: {}, lastPage: null};
      }
      if (!data.forms || typeof data.forms !== 'object') {
        data.forms = {};
      }
      if (!data.lastPage || typeof data.lastPage !== 'object') {
        data.lastPage = null;
      }
      return data;
    }
    catch (e) {
      return {forms: {}, lastPage: null};
    }
  }

  function saveStorage(storage) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(storage));
    }
    catch (e) {
      // Ignore storage errors.
    }
  }

  Drupal.behaviors.massFormContextPageContext = {
    attach: function (context) {
      var onceResult = once('mass-form-context-page', 'html', context);
      if (!onceResult.length) {
        return;
      }

      var mfcSettings = drupalSettings.massFormContext || {};
      var pageContext = mfcSettings.pageContext || {};

      // Only run on real "start pages".
      if (!pageContext.isStartPage) {
        return;
      }

      var params = new URLSearchParams();

      // Always capture the current page URL as referrer.
      params.set('referrer', window.location.href);

      // --- ORG: from drupalSettings OR from meta[name="mg_organization"] ---
      var orgValue = pageContext.org;
      if (!orgValue) {
        var orgMeta = document.querySelector('meta[name="mg_organization"]');
        if (orgMeta && orgMeta.getAttribute('content')) {
          orgValue = orgMeta.getAttribute('content');
        }
      }
      if (orgValue) {
        params.set('org', orgValue);
      }

      // --- PARENT ORG: from drupalSettings OR from meta[name="mg_parent_org"] ---
      var parentOrgValue = pageContext.parentorg;
      if (!parentOrgValue) {
        var parentMeta = document.querySelector('meta[name="mg_parent_org"]');
        if (parentMeta && parentMeta.getAttribute('content')) {
          parentOrgValue = parentMeta.getAttribute('content');
        }
      }
      if (parentOrgValue) {
        params.set('parentorg', parentOrgValue);
      }

      // Site: from drupalSettings if provided, otherwise host.
      if (pageContext.site) {
        params.set('site', pageContext.site);
      }
      else {
        params.set('site', window.location.host);
      }

      var storage = loadStorage();
      storage.lastPage = {
        params: params.toString(),
        timestamp: Date.now()
      };
      saveStorage(storage);
    }
  };
})(Drupal, drupalSettings, once);
