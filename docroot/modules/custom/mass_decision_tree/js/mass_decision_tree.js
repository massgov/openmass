/* global Handlebars */
/**
 * @file
 * Extends Drupal object with mass custom js objects
 *
 * Custom js routing to navigate a user through a decision tree, storing state
 * as we go. This allows us to provide users a more performant and fluid
 * experience while also providing us with analytics about their journey.
 *
 */

(function ($, Handlebars, Drupal) {
  'use strict';

  Drupal.behaviors.MassDecisionTree = {
    attach: function (context, settings) {

      // Container into which rendered templates are put.
      var $content = $('.main-content');

      // Compiled templates.
      var rootContentTpl = Handlebars.compile($('#root-content').html());
      var branchContentTpl = Handlebars.compile($('#branch-content').html());
      var conclusionContentTpl = Handlebars.compile($('#conclusion-content').html());

      // Information about the decision tree.
      var rootId;
      var root;
      var steps;

      // Information about progress through the tree.
      drupalSettings.decisionTree.responses = [];
      var currentStep;
      var currentParams;

      if (typeof drupalSettings.decisionTree !== 'undefined') {
        rootId = Object.keys(drupalSettings.decisionTree)[0];
        var firstTree = drupalSettings.decisionTree[rootId];
        root = firstTree.root;
        steps = firstTree.steps;
      }

      /**
       * Uses current tree state to render Handlebars templates into the DOM.
       */
      var render = {
        content: function () {
          var data = {
            root: root,
            step: currentStep,
            responses: drupalSettings.decisionTree.responses
          };

          switch (currentStepType()) {
            case 'root':
              $content.html(rootContentTpl(data));
              break;
            case 'branch':
              $content.html(branchContentTpl(data));
              break;
            case 'conclusion':
              $content.html(conclusionContentTpl(data));
              break;
          }

          initializeButtons();
          Drupal.behaviors.MassAccordions.create($content);
          if ($('.js-ma-responsive-video').length) {
            window.fitVids($('.js-ma-responsive-video'));
          }
        }
      };

      /**
       * Fires on url hash change, is the driver of all routing / rendering.
       */
      function onHashChange() {
        // Update the destination parameter on edit URLs.
        var hash = window.location.hash;
        if (hash) {
          $('a[href*="?destination"]').each(function () {
            var hrefArray = $(this).attr('href').split('#');
            var href = hrefArray[0] + encodeURIComponent(hash);
            $(this).attr('href', href);
          });
        }

        // Get the latest params, update responses, and update displayed step.
        getParams();
        updateResponsesFromParam();
        displayStep(currentParams.step);

        // scroll newly-rendered section into view
        $('html,body').animate({
          // add 100px buffer to top of section
          scrollTop: $content.offset().top - 100
        });
      }

      /**
       * Sets currentParams to an array of hash params mapped to their values.
       */
      function getParams() {
        // Trim first character ('#') and split on '&' to separate params.
        var hashParams = window.location.hash.substring(1).split('&');
        var params = [];
        var i = 0;
        var l = hashParams.length;
        for (; i < l; i++) {
          // Separate param and value. Ignore params with an empty value.
          var paramComponents = hashParams[i].split('=');
          if (paramComponents.length === 2 && paramComponents[1] !== '') {
            params[paramComponents[0]] = paramComponents[1];
          }
        }

        // For readability, change s to step and p to responses.
        if (params.s) {
          params.step = params.s;
          delete params.s;
        }
        if (params.p) {
          params.responses = params.p;
          delete params.p;
        }

        currentParams = params;
      }

      /**
       * Concatenate params to get a hash which encodes all of them.
       * @return {string} A string, usable as a hash, containing the params.
       */
      function concatParams() {
        var paramComponents = [];
        for (var param in currentParams) {
          if (Object.prototype.hasOwnProperty.call(currentParams, param) && currentParams[param] !== '') {
            var val = currentParams[param];
            if (param === 'responses') {
              param = 'p';
            }
            if (param === 'step') {
              param = 's';
            }
            paramComponents.push(param + '=' + val);
          }
        }
        return paramComponents.join('&');
      }

      /**
       * Updates the currentParams.responses property to match the current set
       * of decision tree responses. Should be used whenever responses change.
       */
      function updateParamFromResponses() {
        var i = 0;
        var l = drupalSettings.decisionTree.responses.length;
        var responseIDs = [];
        for (; i < l; i++) {
          responseIDs.push(drupalSettings.decisionTree.responses[i].id);
        }
        currentParams.responses = responseIDs.join(',');
      }

      /**
       * Updates the the current set of decision tree responses to match
       * currentParams.responses. Should be used whenever params change.
       */
      function updateResponsesFromParam() {
        drupalSettings.decisionTree.responses = [];
        if (currentParams.responses) {
          var stepIds = currentParams.responses.split(',');

          // startId was implicitly visited, and is needed for looking up
          // responses. However, it should not be directly traversed, so i = 1.
          stepIds.unshift(root.startId);
          var i = 1;
          var l = stepIds.length;
          for (; i < l; i++) {

            // Look up the previous step to find the response which got to the
            // current step.
            var previousStep = steps[stepIds[i - 1]];
            if (previousStep) {
              var currentStepId = stepIds[i];
              var responses = previousStep.responses.filter(responsesById(currentStepId));
              if (responses.length) {
                drupalSettings.decisionTree.responses.push(responses[0]);
              }
            }
          }
        }
      }

      /**
       * Updates the current question to given ID and re-renders templates.
       * @param {int} id the ID of the step to display
       */
      function displayStep(id) {
        // If no id is passed, reset currentStep to render the root node.
        if (typeof id === 'undefined') {
          currentStep = null;
          id = rootId;
        }
        else {
          currentStep = steps[id];
          updateDatalayer(id);
        }
        $('#decision-tree-current-node').attr('value', id);

        render.content();
      }

      /**
       * Filter function to find items matching a specific id.
       * @param {string} id The object ID to search for
       * @return {Function} The internal comparison function.
       */
      function responsesById(id) {
        return function (obj) {
          return parseInt(obj.id) === parseInt(id);
        };
      }

      /**
       * Pushed the newly rendered node's data into datalayer for analytics.
       * @param {int} id the ID of the step to display.
       */
      function updateDatalayer(id) {
        // If we're not on the root node, pull from steps.
        if (id !== rootId) {
          var step = steps[id];
          var dataLayer = window.dataLayer || [];
          dataLayer.push({
            entityBundle: 'decision_tree_' + step.type,
            entityId: id,
            entityLabel: step.text,
            entityLangcode: step.langcode,
            entityVid: step.vid,
            entityUid: step.uid,
            entityCreated: step.created,
            entityStatus: step.status,
            entityName: step.name
          });
        }
      }

      /**
       * Returns a string identifying what type of step the tree is on.
       * @return {string} The name of the current step type
       */
      function currentStepType() {
        return currentStep ? currentStep.type : 'root';
      }

      /**
       * Adds custom button/link functionality. Used after content is rendered.
       */
      function initializeButtons() {
        // Start the decision tree.
        $('.ma__decision-tree-node a.start').click(function () {
          currentParams.step = root.startId;
          window.location.hash = concatParams();
          return false;
        });

        // Go back to the root node.
        $('.ma__decision-tree-node .restart a').click(function () {
          window.location.href = window.location.href.split('#')[0];
          return false;
        });

        // Log response and advance to the selected step.
        $('.ma__decision-tree-node__responses button').click(function () {
          var responseID = $(this).data('response');
          currentParams.step = responseID;

          // Find the response object with a matching ID and log it.
          var responses = currentStep.responses.filter(responsesById(responseID));
          if (responses.length) {
            drupalSettings.decisionTree.responses.push(responses[0]);
            updateParamFromResponses();
          }

          window.location.hash = concatParams();
        });

        // Remove the most recent response and go back a step.
        $('.ma__decision-tree-node .back a').click(function () {

          // If this was the first step, go to the root node.
          if (currentStep.id === root.startId) {
            window.location.href = window.location.href.split('#')[0];
          }
          else {
            // Update the most recent response and update params.
            drupalSettings.decisionTree.responses.pop();
            updateParamFromResponses();

            // If there are responses left, look at the most recent to see what
            // question it leads to.
            if (drupalSettings.decisionTree.responses.length) {
              var len = drupalSettings.decisionTree.responses.length;
              var response = drupalSettings.decisionTree.responses[len - 1];
              currentParams.step = response.id;
            }
            else {
              currentParams.step = root.startId;
            }
            window.location.hash = concatParams();
          }
          return false;
        });

        // Add destination to the edit link, if one exists.
        $('.ma__decision-tree-node a.edit-link').each(function () {
          var destination = '?destination=' +
            encodeURIComponent(window.location.pathname + window.location.hash);
          $(this).attr('href', $(this).attr('href') + destination);
        });

        window.onhashchange = onHashChange;
      }

      // Render the templates for the root node.
      var $elements = $(once('decisionTreeInit', 'div.main-content', context));
      $elements.each(function (index) {
        onHashChange();
      });
    }
  };

})(jQuery, Handlebars, Drupal, drupalSettings);
