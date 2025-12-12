/**
 * @file
 * Collapse messages on scroll.
 */

(function (Drupal, once) {

  'use strict';

  /**
   * Slide up and remove an element with animation.
   */
  function slideUpAndRemove(element) {
    element.style.overflow = 'hidden';
    element.style.transition = 'height 0.5s ease, opacity 0.5s ease';
    const height = element.offsetHeight;
    element.style.height = height + 'px';

    requestAnimationFrame(function () {
      element.style.height = '0';
      element.style.opacity = '0';
    });

    element.addEventListener('transitionend', function handler() {
      element.removeEventListener('transitionend', handler);
      element.remove();
    }, { once: true });
  }

  /**
   * Handle close button click.
   */
  function handleCloseClick(event) {
    event.preventDefault();
    const messageItem = event.currentTarget.closest('.messages-list__item');
    if (messageItem) {
      slideUpAndRemove(messageItem);
    }
  }

  Drupal.behaviors.massDashboardTabs = {
    attach: function (context) {


      once('mass-dashboard-messages', '.messages-list__item', context).forEach(function (messageItem) {

        // Get the message type from the title for better accessibility.
        const messageTitle = messageItem.querySelector('.messages__title');
        const messageType = messageTitle ? messageTitle.textContent.trim() : 'message';

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'mass-dashboard-messages-close-button';
        closeButton.setAttribute('aria-label', 'Close ' + messageType);

        // Create span with × symbol hidden from screen readers.
        const closeIcon = document.createElement('span');
        closeIcon.setAttribute('aria-hidden', 'true');
        closeIcon.textContent = '×';
        closeButton.appendChild(closeIcon);

        closeButton.addEventListener('click', handleCloseClick);

        // Append button after content for better accessibility (focus order).
        // CSS should be used to position it visually at the top-right.
        messageItem.appendChild(closeButton);
      });
    }
  };

})(Drupal, once);
