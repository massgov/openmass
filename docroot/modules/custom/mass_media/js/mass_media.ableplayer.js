/**
 * @file
 * Initializes Able Player for both initial and AJAX-rendered media.
 *
 * @param {object} Drupal
 *   Drupal behavior registry.
 * @param {Function} once
 *   Drupal once utility.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.massMediaAblePlayer = {
    attach(context) {
      once('mass-media-ableplayer', '[data-mass-able-player]', context).forEach((video) => {
        new window.AblePlayer(video);
      });
    }
  };
})(Drupal, once);
