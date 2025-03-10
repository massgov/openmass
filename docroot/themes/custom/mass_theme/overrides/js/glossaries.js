(async (Drupal, drupalSettings) => {

  const { glossaries, terms } = drupalSettings.glossaries;
  const searchRegexes = createSearchRegexes(Object.keys(terms));
  const UNACCEPTABLE_SELECTORS = 'script, style, a, button, h1, h2, h3, h4, h5, h6';
  const mainContentSelector = 'main .main-content .page-content';
  const mainContent = document.querySelector(mainContentSelector);

  Drupal.behaviors.glossaries = {
    attach: async (context) => {
      // Scan page text for glossary terms and inject tooltips.
      console.time('scan tooltip time');
      console.clear();
      const matches = findMatches(context);
      highlightMatches(matches, Object.keys(terms), terms);
      console.timeEnd('scan tooltip time');
    }
  };

  /**
   * Create a list of regexes for the search strings.
   * @param {string[]} searchStrings - The strings to search for.
   * @returns {Map} A map where string -> regex
   */
  function createSearchRegexes(searchStrings) {
    const searches = new Map();

    // Create one replacement function instead of creating it in the loop
    const escapeRegex = str => str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    searchStrings.forEach(string => {
      const regex = new RegExp(`\\b${escapeRegex(string)}(?:es|s)?\\b`, 'i');
      searches.set(string, regex);
    });

    return searches;
  }

  /**
   * Determine if a node should be accepted by the TreeWalker.
   * This step filters out nodes before we look for matching terms.
   * @param {Text} node - The node to check.
   * @returns {NodeFilter.FILTER_ACCEPT | NodeFilter.FILTER_REJECT} Whether the node should be accepted or rejected.
   */
  function shouldAcceptNode(node) {
    // Check text content first since it's the cheapest operation
    if (node.textContent.trim() === '') {
      return NodeFilter.FILTER_REJECT;
    }

    // Cache the parent element reference
    const parent = node.parentElement;
    if (!parent) {
      return NodeFilter.FILTER_REJECT;
    }

    // Do the closest() check
    if (parent.closest(UNACCEPTABLE_SELECTORS)) {
      return NodeFilter.FILTER_REJECT;
    }

    // Only create range if we passed the other checks
    const range = document.createRange();
    range.selectNode(node);
    const rect = range.getBoundingClientRect();
    range.detach();

    return (rect.width <= 1 || rect.height <= 1)
      ? NodeFilter.FILTER_REJECT
      : NodeFilter.FILTER_ACCEPT;
  }

  /**
   * Find all matches of the search strings in the given context.
   * @param {Node} context - The context to search in.
   * @param {Map<string, regex>} searchRegexes - The strings to search for.
   * @returns {Object[]} A list of matches.
   */
  function findMatches(context) {
    const matches = [];

    // Use cached mainContent
    if (!mainContent) return matches;

    // Scan the main content of the page on initial page load.
    // Scan the context (if in main content) on subsequent behavior runs.
    let scanRoot;
    if (mainContent.contains(context)) {
      scanRoot = context;
    } else if (context.contains(mainContent)) {
      scanRoot = mainContent;
    } else {
      return matches; // Exit if there's no relationship between context and main-content
    }

    // Collect text nodes that pass shouldAcceptNode
    const walker = document.createTreeWalker(
      scanRoot,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode: shouldAcceptNode,
      }
    );

    // Iterate over nodes to find useages of  terms.
    let node;
    while (node = walker.nextNode()) {
      const text = node.textContent;

      // Quit looping over nodes if we've used all the terms.
      if (!searchRegexes.size) {
        break;
      }

      // Loop over unfound terms
      for (const [searchString, searchRegex] of searchRegexes) {
        if (text.match(searchRegex)) {
          matches.push({
            node,
            searchString,
            searchRegex,
          });

          // Avoid searching for this term again.
          searchRegexes.delete(searchString);
        }
      }
    }

    return matches;
  }

  /**
   * Create HTML content for a tooltip by combining definitions with glossary source links.
   * @param {Object.<string, string>} definitions - Object mapping glossary UUIDs to definition text
   * @returns {string} HTML string containing definition text and source citation links
   */
  function createTooltipContent(definitions) {
    return Object.entries(definitions).map(([uuid, definition]) => {
      const { name, url } = glossaries[uuid];
      return  `${definition}<cite><a href="${url}">${name}</a></cite>`
    }).join('<hr/>')
  }

  /**
   * Generate a unique ID for a tooltip.
   * @returns {string} A unique ID for a tooltip.
   */
  function generateTooltipId() {
    return 'tooltip_' + Math.random().toString(36).substring(2, 11);
  }

  /**
   * Create the markup for a tooltip.
   * @param {string} text - The text to display in the tooltip.
   * @param {string} tooltipId - The ID of the tooltip.
   * @param {string} definition - The definition of the tooltip.
   * @returns {string} The markup for a tooltip.
   */
  function createTooltip(text, definition) {
    const tooltipId = generateTooltipId();
    const markup = `
      <span class="ma__tooltip">
        <span class="ma__tooltip__inner">
          <input
            id="${tooltipId}"
            type="checkbox"
            class="ma__tooltip__control"
            aria-label="show more information about ${text}"
            aria-hidden="true" />
          <label
            for="${tooltipId}"
            class="ma__tooltip__open"
            aria-labelledby="${tooltipId}"
            aria-hidden="true">
            ${text}
            <svg aria-hidden="true" focusable="false"><use xlink:href="#dabfd50784945c0631c8efda338195d5.0"></use></svg><svg xmlns="http://www.w3.org/2000/svg" style="display: none"><symbol xmlns="http://www.w3.org/2000/svg" aria-hidden="true" version="1.1" viewBox="0 0 16 16" id="dabfd50784945c0631c8efda338195d5.0"><path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0zm0 12.17a.84.84 0 0 1-.83-.84.83.83 0 1 1 1.67 0c0 .46-.38.84-.84.84zm1.3-3.96c-.6.65-.62 1.01-.62 1.46H7.35c0-.99.01-1.42.95-2.32.38-.36.69-.65.64-1.21-.04-.54-.48-.82-.9-.82-.48 0-1.03.35-1.03 1.35H5.67c0-1.6.94-2.64 2.4-2.64.68 0 1.29.23 1.7.64.37.38.57.91.56 1.53-.01.92-.57 1.53-1.02 2.01z"/></symbol></svg>
          </label>
          <section class="ma__tooltip__modal ma__tooltip__modal--below">
            <div class="ma__tooltip__container">
              <label
                for="${tooltipId}"
                class="ma__tooltip__close"
                tabindex="-1"
                aria-labelledby="${tooltipId}"
                aria-hidden="true">Close</label>
              <div class="ma__tooltip__message">
                ${definition}
              </div>
            </div>
          </section>
        </span>
      </span>`;

      const element = Document.parseHTMLUnsafe(markup).body.firstElementChild;
      return element;
  }

  /**
   * Highlight matches in the text.
   * @param {Array<{node: Node, searchRegex: RegExp, searchString: string}>} matches - Array of matches containing text nodes, search regex and search string
   * @returns {void}
   */
  function highlightMatches(matches) {
    matches.forEach(({node, searchRegex, searchString}) => {

      let text = node.textContent;

      // Find the match position and content
      const match = searchRegex.exec(text);
      if (!match) {
        return;
      }

      const matchStart = match.index;
      const matchEnd = matchStart + match[0].length;

      // Create text nodes for before and after the match
      const beforeText = document.createTextNode(text.substring(0, matchStart));
      const afterText = document.createTextNode(text.substring(matchEnd));

      // Create the tooltip.
      const definition = createTooltipContent(terms[searchString])
      const tooltip = createTooltip(match[0], definition)

      // Replace the original text node.
      const parent = node.parentElement;

      parent.insertBefore(beforeText, node);
      parent.insertBefore(tooltip, node);
      parent.insertBefore(afterText, node);
      parent.removeChild(node);
    });
  }
})(Drupal, drupalSettings);
