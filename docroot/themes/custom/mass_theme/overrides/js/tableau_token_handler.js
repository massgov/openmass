/**
 * @file
 * Tableau Token Handler (v3 – Connected Apps).
 *
 * Loads one visualization at a time. Every JWT these embeds use is minted for
 * the same Tableau user, and an embed session lives in a single cookie on the
 * Tableau host. Two vizzes establishing a session at once therefore overwrite
 * each other's session and one of them falls back to the Tableau sign-in form.
 * The queue below guarantees that at most one viz is between "token requested"
 * and "first interactive" at any moment.
 */

((Drupal, drupalSettings, once) => {
  'use strict';

  const config = Object.assign({
    // Wait for a viz to become interactive before starting the next one.
    loadTimeout: 15000,
    // Wait for the token endpoint.
    tokenTimeout: 8000,
    // Breathing room after a viz is interactive, before the next signin.
    settleDelay: 300,
    // Start a viz this far before it scrolls into view.
    rootMargin: '600px',
    // Set to false to load everything immediately in DOM order.
    lazy: true,
  }, drupalSettings.massTableau || {});

  // Module-level state, shared by every behaviour invocation on this page.
  // This is what keeps a second attach() from starting a second worker (R2).
  const queue = [];
  let pumping = false;

  const wait = (ms) => new Promise((resolve) => { setTimeout(resolve, ms); });

  /**
   * Runs the queue, one viz at a time. Safe to call at any time.
   */
  async function pump() {
    if (pumping) {
      return;
    }
    pumping = true;
    try {
      // The SDK is a deferred ES module and weighs ~2.5 MB. Without this the
      // load timeout would start ticking before <tableau-viz> even exists (R6).
      await customElements.whenDefined('tableau-viz');

      while (queue.length) {
        const placeholder = queue.shift();
        // It may have been removed while we were awaiting the previous viz (R10).
        if (placeholder.isConnected) {
          await loadOne(placeholder);
          await wait(config.settleDelay);
        }
      }
    }
    finally {
      // No await between the loop test and this line — see R2/R9.
      pumping = false;
    }
  }

  function enqueue(placeholder) {
    queue.push(placeholder);
    // Restart the worker if it already drained the queue (R9).
    pump();
  }

  /**
   * Fetches a token and renders a single viz. Resolves once that viz is
   * interactive, has failed, or has run out of time.
   */
  async function loadOne(placeholder) {
    const tokenUrl = placeholder.dataset.tokenUrl;
    if (!tokenUrl) {
      renderFailure(placeholder, 'no token URL configured');
      return;
    }

    let token;
    try {
      // Fetched here, not up front: tokens live 600 s and a long queue would
      // hand an expired JWT to the tail of the list (R7).
      token = await fetchToken(tokenUrl);
    }
    catch (error) {
      renderFailure(placeholder, error.message);
      return;
    }

    if (!placeholder.isConnected) {
      return;
    }

    const viz = buildViz(placeholder, token);
    // Listeners must be live before the element enters the DOM (R5).
    const finished = waitForViz(viz);
    placeholder.replaceWith(viz);
    await finished;
  }

  async function fetchToken(url) {
    const controller = new AbortController();
    const timer = setTimeout(() => { controller.abort(); }, config.tokenTimeout);
    try {
      const response = await fetch(url, {
        signal: controller.signal,
        // The endpoint sends no cache headers; single-use JWTs must not be
        // served from a cache (R8).
        cache: 'no-store',
        credentials: 'omit',
      });
      if (!response.ok) {
        throw new Error(`token endpoint returned ${response.status}`);
      }
      const data = await response.json();
      if (!data.token) {
        throw new Error(data.message || 'no token in response');
      }
      return data.token;
    }
    finally {
      clearTimeout(timer);
    }
  }

  /**
   * Resolves on the first of: interactive, load error, timeout (R4).
   */
  function waitForViz(viz) {
    return new Promise((resolve) => {
      let settled = false;
      let timer = null;

      const finish = (outcome, detail) => {
        if (settled) {
          return;
        }
        settled = true;
        clearTimeout(timer);
        viz.removeEventListener('firstinteractive', onInteractive);
        viz.removeEventListener('vizloaderror', onError);
        if (outcome !== 'interactive') {
          console.warn('Tableau viz did not become interactive:', outcome, detail || '');
        }
        resolve(outcome);
      };

      const onInteractive = () => { finish('interactive'); };
      const onError = (event) => { finish('error', event.detail); };

      viz.addEventListener('firstinteractive', onInteractive);
      viz.addEventListener('vizloaderror', onError);
      timer = setTimeout(() => { finish('timeout'); }, config.loadTimeout);
    });
  }

  function buildViz(placeholder, token) {
    const viz = document.createElement('tableau-viz');
    viz.setAttribute('src', placeholder.dataset.tableauUrl);
    viz.setAttribute('id', placeholder.id);
    viz.setAttribute('toolbar', placeholder.dataset.toolbar || 'hidden');
    viz.setAttribute('hide-tabs', '');
    viz.setAttribute('token', token);

    const { dataDetails, shareOptions } = placeholder.dataset;
    if (dataDetails === 'show' || dataDetails === 'hide') {
      viz.appendChild(customParameter(':dataDetails', dataDetails === 'hide' ? 'no' : 'yes'));
    }
    if (shareOptions === 'show' || shareOptions === 'hide') {
      viz.appendChild(customParameter(':showShareOptions', shareOptions === 'hide' ? 'false' : 'true'));
    }
    return viz;
  }

  function customParameter(name, value) {
    const parameter = document.createElement('custom-parameter');
    parameter.setAttribute('name', name);
    parameter.setAttribute('value', value);
    return parameter;
  }

  /**
   * Replaces a placeholder that cannot be rendered with a readable message,
   * instead of leaving an empty div behind.
   */
  function renderFailure(placeholder, reason) {
    console.error('Tableau embed failed:', reason);
    const message = document.createElement('div');
    message.className = 'ma_tableau_error';
    message.textContent = Drupal.t('This visualization could not be loaded. Please refresh the page or try again later.');
    placeholder.replaceWith(message);
  }

  const observer = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries, self) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          self.unobserve(entry.target);
          enqueue(entry.target);
        }
      });
    }, { rootMargin: config.rootMargin })
    : null;

  Drupal.behaviors.tableauTokenHandler = {
    attach(context) {
      // once() keeps a repeated attach from queueing the same placeholder
      // twice (R3).
      const placeholders = once('tableau-token-handler', '.ma_tableau_placeholder', context);
      placeholders.forEach((placeholder) => {
        if (observer && config.lazy) {
          observer.observe(placeholder);
        }
        else {
          enqueue(placeholder);
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
