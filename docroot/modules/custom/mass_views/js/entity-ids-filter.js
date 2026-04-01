/**
 * @file
 * Popup textarea widget for the Entity IDs views filter.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Parses a raw string of IDs into a sorted, deduplicated array of integers.
   *
   * @param {string} raw
   *   The raw string (newlines, commas, spaces as separators).
   *
   * @return {number[]}
   *   Array of positive integers.
   */
  function parseIds(raw) {
    if (!raw || !raw.trim()) {
      return [];
    }
    var seen = {};
    var result = [];
    var parts = raw.split(/[\s,]+/);
    for (var i = 0; i < parts.length; i++) {
      var num = parseInt(parts[i], 10);
      if (num > 0 && !seen[num]) {
        seen[num] = true;
        result.push(num);
      }
    }
    return result;
  }

  /**
   * Renders tag chips for the given IDs into the container element.
   *
   * @param {HTMLElement} container
   *   The tags container element.
   * @param {number[]} ids
   *   Array of active IDs.
   * @param {HTMLInputElement} hiddenInput
   *   The hidden input element holding the comma-separated value.
   * @param {HTMLElement} clearBtn
   *   The "Clear all" button element.
   */
  function renderTags(container, ids, hiddenInput, clearBtn) {
    container.innerHTML = '';
    for (var i = 0; i < ids.length; i++) {
      var tag = document.createElement('span');
      tag.className = 'entity-ids-tag';
      tag.textContent = ids[i] + ' ';

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'entity-ids-tag-remove';
      removeBtn.textContent = '\u00d7';
      removeBtn.setAttribute('aria-label', Drupal.t('Remove @id', {'@id': ids[i]}));
      removeBtn.dataset.id = ids[i];

      tag.appendChild(removeBtn);
      container.appendChild(tag);
    }

    clearBtn.style.display = ids.length > 0 ? '' : 'none';
  }

  /**
   * Creates the popup overlay element.
   *
   * @param {string} label
   *   The filter label for the popup title.
   *
   * @return {HTMLElement}
   *   The overlay element.
   */
  function createPopup(label) {
    var overlay = document.createElement('div');
    overlay.className = 'entity-ids-popup-overlay';

    var popup = document.createElement('div');
    popup.className = 'entity-ids-popup';

    var title = document.createElement('h3');
    title.className = 'entity-ids-popup-title';
    title.textContent = label;
    popup.appendChild(title);

    var descriptionId = 'entity-ids-popup-description-' + Math.random().toString(36).slice(2, 8);
    var description = document.createElement('p');
    description.className = 'entity-ids-popup-description';
    description.id = descriptionId;
    description.textContent = Drupal.t('Enter one ID per line. You can also use commas or spaces as separators. Limit batch to 300 IDs or less.');
    popup.appendChild(description);

    var textarea = document.createElement('textarea');
    textarea.className = 'entity-ids-popup-textarea';
    textarea.rows = 12;
    textarea.setAttribute('aria-describedby', descriptionId);
    popup.appendChild(textarea);

    var actions = document.createElement('div');
    actions.className = 'entity-ids-popup-actions';

    var applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'entity-ids-popup-apply button button--primary';
    applyBtn.textContent = Drupal.t('Apply');
    actions.appendChild(applyBtn);

    var cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'entity-ids-popup-cancel button';
    cancelBtn.textContent = Drupal.t('Cancel');
    actions.appendChild(cancelBtn);

    popup.appendChild(actions);
    overlay.appendChild(popup);

    return overlay;
  }

  Drupal.behaviors.massViewsEntityIdsFilter = {
    attach: function (context) {
      var elements = once('mass-entity-ids-filter', 'input.mass-views-entity-ids-value', context);

      for (var idx = 0; idx < elements.length; idx++) {
        (function (hiddenInput) {
          var label = hiddenInput.getAttribute('data-entity-ids-label') || Drupal.t('Entity IDs');
          var filterDescription = hiddenInput.getAttribute('data-entity-ids-description') || '';
          var form = hiddenInput.closest('form');

          // Build wrapper.
          var wrapper = document.createElement('div');
          wrapper.className = 'entity-ids-filter-wrapper';

          // Description text.
          if (filterDescription) {
            var descriptionText = document.createElement('span');
            descriptionText.className = 'entity-ids-description';
            descriptionText.textContent = filterDescription;
            wrapper.appendChild(descriptionText);
          }

          // Open popup button.
          var openBtn = document.createElement('button');
          openBtn.type = 'button';
          openBtn.className = 'entity-ids-open-popup button';
          openBtn.textContent = label;
          wrapper.appendChild(openBtn);

          // Tags container.
          var tagsContainer = document.createElement('div');
          tagsContainer.className = 'entity-ids-tags';
          wrapper.appendChild(tagsContainer);

          // Clear all button.
          var clearAllBtn = document.createElement('button');
          clearAllBtn.type = 'button';
          clearAllBtn.className = 'entity-ids-clear-all button';
          clearAllBtn.textContent = Drupal.t('Clear all');
          clearAllBtn.style.display = 'none';
          wrapper.appendChild(clearAllBtn);

          // Insert wrapper at the top of the views exposed form.
          if (form) {
            form.insertBefore(wrapper, form.firstChild);
          }
          else {
            hiddenInput.parentNode.insertBefore(wrapper, hiddenInput.nextSibling);
          }

          // Initial render of tags from current value.
          var currentIds = parseIds(hiddenInput.value);
          renderTags(tagsContainer, currentIds, hiddenInput, clearAllBtn);

          // Create popup (reuse single instance).
          var overlay = createPopup(label);
          var textarea = overlay.querySelector('.entity-ids-popup-textarea');
          var applyBtn = overlay.querySelector('.entity-ids-popup-apply');
          var cancelBtn = overlay.querySelector('.entity-ids-popup-cancel');
          document.body.appendChild(overlay);

          function openPopup() {
            var ids = parseIds(hiddenInput.value);
            textarea.value = ids.join('\n');
            overlay.style.display = 'flex';
            textarea.focus();
          }

          function closePopup() {
            overlay.style.display = 'none';
            openBtn.focus();
          }

          function applyAndSubmit() {
            var ids = parseIds(textarea.value);
            hiddenInput.value = ids.join(',');
            renderTags(tagsContainer, ids, hiddenInput, clearAllBtn);
            closePopup();
            if (form) {
              form.submit();
            }
          }

          // Event: open popup.
          openBtn.addEventListener('click', openPopup);

          // Event: apply.
          applyBtn.addEventListener('click', applyAndSubmit);

          // Event: cancel.
          cancelBtn.addEventListener('click', closePopup);

          // Event: overlay background click closes popup.
          overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
              closePopup();
            }
          });

          // Event: Escape key closes popup.
          overlay.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
              closePopup();
            }
          });

          // Event: remove individual tag.
          tagsContainer.addEventListener('click', function (e) {
            var removeBtn = e.target.closest('.entity-ids-tag-remove');
            if (!removeBtn) {
              return;
            }
            var removeId = parseInt(removeBtn.dataset.id, 10);
            var ids = parseIds(hiddenInput.value).filter(function (id) {
              return id !== removeId;
            });
            hiddenInput.value = ids.join(',');
            renderTags(tagsContainer, ids, hiddenInput, clearAllBtn);
            if (form) {
              form.submit();
            }
          });

          // Event: clear all.
          clearAllBtn.addEventListener('click', function () {
            hiddenInput.value = '';
            renderTags(tagsContainer, [], hiddenInput, clearAllBtn);
            if (form) {
              form.submit();
            }
          });
        })(elements[idx]);
      }
    }
  };

})(Drupal, once);
