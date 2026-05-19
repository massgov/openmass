/**
 * Augments the Owner Groups (field_content_organization) input when the
 * author edits the Organizations (field_organizations) widget.
 *
 * field_organizations is rendered as one autocomplete input per
 * referenced node (name="field_organizations[N][target_id]"), with
 * "Add another item" / "Remove" AJAX buttons. We compute the set of
 * currently referenced org_page NIDs by reading every input on each
 * sync pass; the Drupal AJAX rebuild on add/remove re-fires
 * Drupal.attachBehaviors, which re-attaches once() handlers.
 *
 * On diff we fetch /mass-org-access/lookup-user-orgs for added NIDs and
 * append the returned user_organization terms to
 * field_content_organization. We remember which terms each org brought
 * in (Map<orgNid, Set<tid>>) so when an org is removed we drop only the
 * terms that organization auto-added and that are no longer referenced
 * by any remaining tracked org. Terms a user added by hand are never
 * touched.
 *
 * Nothing is persisted before form submit — the change lives in the
 * hidden OOG autocomplete input until the user saves.
 *
 * @param {Drupal} Drupal Drupal global object providing behaviors registry.
 * @param {Function} once Core/once helper for one-time element processing.
 */

(function (Drupal, once) {
  'use strict';

  const ENDPOINT = '/mass-org-access/lookup-user-orgs';
  // Each bundle that auto-derives field_organizations (binder, decision,
  // person…) writes its organization picks into a bundle-specific field.
  // mass_validation later unions them into field_organizations on
  // presave, but for the JS augmentation we have to listen to the
  // editor-facing fields directly.
  const ORG_FIELD_PREFIXES = [
    'field_organizations',
    'field_binder_ref_organization',
    'field_decision_ref_organization',
    'field_person_ref_org'
  ];
  const ORG_INPUT_SELECTOR = ORG_FIELD_PREFIXES
    .map(function (name) {
      return 'input.form-autocomplete[name^="' + name + '["][name$="[target_id]"]';
    })
    .join(',');
  const ORG_WRAPPER_SELECTOR = ORG_FIELD_PREFIXES
    .map(function (name) {
      return '.field--name-' + name.replace(/_/g, '-');
    })
    .join(',');
  const OOG_SELECTOR =
    'input.form-autocomplete[name="field_content_organization[target_id]"]';

  Drupal.behaviors.massOrgAccessAugmentOogFromOrgs = {
    attach: function (context) {
      const forms = once('oog-from-organizations', 'form', context);
      forms.forEach(function (form) {
        if (!form.querySelector(OOG_SELECTOR)) {
          return;
        }
        if (!form.querySelector(ORG_INPUT_SELECTOR)) {
          return;
        }
        attachForm(form);
      });
    }
  };

  /**
   * Wires sync events for one form (org input edits + AJAX rebuilds).
   *
   * @param {HTMLFormElement} form The form element.
   */
  function attachForm(form) {
    const oogInput = form.querySelector(OOG_SELECTOR);
    const trackedByOrg = new Map();
    let lastOrgNids = collectOrgNids(form);
    let syncing = false;

    const run = function () {
      if (syncing) {
        return;
      }
      syncing = true;
      syncOnce(form, oogInput, trackedByOrg, lastOrgNids).then(
        function (nextNids) {
          lastOrgNids = nextNids;
          syncing = false;
        },
        function () {
          syncing = false;
        }
      );
    };

    // jQuery UI autocomplete writes to the input via .val(), which does
    // not fire native change/input events — fast paths catch only
    // direct typing / .dispatchEvent() calls.
    form.addEventListener('change', function (event) {
      if (event.target && event.target.matches && event.target.matches(ORG_INPUT_SELECTOR)) {
        run();
      }
    }, true);
    form.addEventListener('input', function (event) {
      if (event.target && event.target.matches && event.target.matches(ORG_INPUT_SELECTOR)) {
        run();
      }
    }, true);

    // The Add another / Remove buttons trigger an AJAX form rebuild that
    // replaces the inputs — re-sync when the wrapper subtree mutates.
    const wrappers = form.querySelectorAll(ORG_WRAPPER_SELECTOR);
    wrappers.forEach(function (wrapper) {
      new MutationObserver(run).observe(wrapper, { childList: true, subtree: true });
    });

    // Polling fallback — catches autocomplete selects (jQuery .val(),
    // no events) and direct programmatic writes. Sync only triggers
    // when the collected set of NIDs actually differs.
    let pollSnapshot = serializeNids(lastOrgNids);
    setInterval(function () {
      const snapshot = serializeNids(collectOrgNids(form));
      if (snapshot !== pollSnapshot) {
        pollSnapshot = snapshot;
        run();
      }
    }, 500);
  }

  /**
   * Stable string encoding for a Set<string> so we can cheap-compare snapshots.
   *
   * @param {Set<string>} set Set of NIDs.
   *
   * @return {string} Sorted, comma-joined NIDs.
   */
  function serializeNids(set) {
    return [...set].sort().join(',');
  }

  /**
   * Single diff pass: figure out added/removed org NIDs, mutate OOG.
   *
   * @param {HTMLFormElement} form        Edit form.
   * @param {HTMLInputElement} oogInput   field_content_organization input.
   * @param {Map<string,Set<string>>} trackedByOrg Reference map of auto-adds.
   * @param {Set<string>} previousNids    Last known set of org NIDs.
   *
   * @return {Promise<Set<string>>} The current set of org NIDs.
   */
  async function syncOnce(form, oogInput, trackedByOrg, previousNids) {
    const currentNids = collectOrgNids(form);
    const added = [...currentNids].filter(function (n) { return !previousNids.has(n); });
    const removed = [...previousNids].filter(function (n) { return !currentNids.has(n); });
    if (added.length === 0 && removed.length === 0) {
      return currentNids;
    }

    let oogTerms = parseTagged(oogInput.value);

    if (added.length) {
      const fetched = await fetchTermsForOrgs(added);
      const existingIds = new Set(oogTerms.map(function (t) { return t.id; }));
      added.forEach(function (orgNid) {
        const terms = fetched[orgNid] || [];
        const tracked = new Set();
        trackedByOrg.set(orgNid, tracked);
        terms.forEach(function (term) {
          const tid = String(term.tid);
          tracked.add(tid);
          if (!existingIds.has(tid)) {
            oogTerms.push({ id: tid, label: term.label });
            existingIds.add(tid);
          }
        });
      });
    }

    if (removed.length) {
      const stillTracked = new Set();
      trackedByOrg.forEach(function (tids, orgNid) {
        if (currentNids.has(orgNid)) {
          tids.forEach(function (t) { stillTracked.add(t); });
        }
      });
      const toDrop = new Set();
      removed.forEach(function (orgNid) {
        const tids = trackedByOrg.get(orgNid);
        if (!tids) {
          return;
        }
        tids.forEach(function (tid) {
          if (!stillTracked.has(tid)) {
            toDrop.add(tid);
          }
        });
        trackedByOrg.delete(orgNid);
      });
      if (toDrop.size) {
        oogTerms = oogTerms.filter(function (t) { return !toDrop.has(t.id); });
      }
    }

    const nextValue = formatTagged(oogTerms);
    if (nextValue !== oogInput.value) {
      oogInput.value = nextValue;
      oogInput.dispatchEvent(new Event('change', { bubbles: true }));
      oogInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    return currentNids;
  }

  /**
   * Reads every field_organizations[N][target_id] input and extracts the
   * "(NID)" of each non-empty value.
   *
   * Drupal's entity autocomplete writes either `Label (NID)` or
   * `Label (NID) - Bundle` (the bundle suffix appears when
   * target_bundles match across multiple bundles). We tolerate both by
   * matching "(NID)" optionally followed by any non-paren text to the
   * end of the string.
   *
   * @param {HTMLFormElement} form Edit form.
   *
   * @return {Set<string>} Org NIDs currently referenced.
   */
  function collectOrgNids(form) {
    const nids = new Set();
    form.querySelectorAll(ORG_INPUT_SELECTOR).forEach(function (input) {
      const m = (input.value || '').match(/\((\d+)\)[^()]*$/);
      if (m) {
        nids.add(m[1]);
      }
    });
    return nids;
  }

  /**
   * GETs the lookup endpoint for the given org_page NIDs.
   *
   * @param {string[]} nids Numeric strings.
   *
   * @return {Promise<Object<string,Array<{tid:number,label:string}>>>} Map.
   */
  async function fetchTermsForOrgs(nids) {
    const params = nids
      .map(function (n) { return 'org_page_nids%5B%5D=' + encodeURIComponent(n); })
      .join('&');
    const response = await fetch(ENDPOINT + '?' + params, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin'
    });
    if (!response.ok) {
      return {};
    }
    const data = await response.json();
    return (data && data.orgs) || {};
  }

  /**
   * Parses Drupal's entity_autocomplete tag format into {id, label} items.
   *
   * @param {string} value Raw autocomplete tag value.
   *
   * @return {Array<{id:string,label:string}>} Parsed items.
   */
  function parseTagged(value) {
    if (!value) {
      return [];
    }
    const raw = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < value.length; i++) {
      const c = value[i];
      if (c === '"') {
        inQuotes = !inQuotes;
        current += c;
        continue;
      }
      if (c === ',' && !inQuotes && value[i + 1] === ' ') {
        raw.push(current);
        current = '';
        i++;
        continue;
      }
      current += c;
    }
    if (current.trim()) {
      raw.push(current);
    }
    return raw
      .map(function (s) {
        s = s.trim();
        const match = s.match(/^(.*)\s*\((\d+)\)\s*$/);
        if (!match) {
          return null;
        }
        let label = match[1].trim();
        if (label.startsWith('"') && label.endsWith('"')) {
          label = label.slice(1, -1).replace(/""/g, '"');
        }
        return { id: match[2], label: label };
      })
      .filter(Boolean);
  }

  /**
   * Renders {id,label}[] back into Drupal's entity_autocomplete tag format.
   *
   * @param {Array<{id:string,label:string}>} items Items to render.
   *
   * @return {string} Comma-separated tagged string.
   */
  function formatTagged(items) {
    return items
      .map(function (item) {
        const needsQuotes = /[",]/.test(item.label);
        const label = needsQuotes
          ? '"' + item.label.replace(/"/g, '""') + '"'
          : item.label;
        return label + ' (' + item.id + ')';
      })
      .join(', ');
  }
})(Drupal, once);
