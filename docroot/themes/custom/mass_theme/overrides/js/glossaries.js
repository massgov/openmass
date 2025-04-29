(async function (Drupal, drupalSettings) {
  'use strict';

  const {terms} = drupalSettings.glossaries;
  const searchRegexes = createSearchRegexes(Object.keys(terms));
  const UNACCEPTABLE_ELEMENTS = [
    'script',
    'style',
    'a',
    'button',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6'
  ];
  const UNACCEPTABLE_CONTAINERS = [
    '.pre-content',
    '.post-content',
    '.ma__page-header',
    '.ma__sticky-toc',
    '.sidebar',
    '.ma__listing-table',
    '.ma__download-link',
    '.ma__contact-list'
  ];
  const UNACCEPTABLE_SELECTORS = [...UNACCEPTABLE_ELEMENTS, ...UNACCEPTABLE_CONTAINERS].join(',');
  const mainContentSelector = 'main';
  const template = document.getElementById('glossary-popup-template');

  Drupal.behaviors.glossaries = {
    attach: async (context) => {
      // Scan page text for glossary terms and inject tooltips.
      const matches = findMatches(context);
      highlightMatches(matches);
    }
  };

  /**
   * Create a list of regexes for the search strings.
   * @param {string[]} searchStrings - The strings to search for.
   * @return {Map} A map where string -> regex
   */
  function createSearchRegexes(searchStrings) {
    const searches = new Map();

    // Create one replacement function instead of creating it in the loop
    const escapeRegex = str => {
      let replaced = str;

      // Replace regex special characters with escaped versions.
      replaced = replaced.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

      // Replace any non-escaped, non alphanumeric characters with wildcards
      replaced = replaced.replace(/[^\w\s]/g, '.?');

      return replaced;
    };

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
   * @return {NodeFilter.FILTER_ACCEPT | NodeFilter.FILTER_REJECT} Whether the node should be accepted or rejected.
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
   * @return {Object[]} A list of matches.
   */
  function findMatches(context) {
    const matches = [];
    const mainContent = document.querySelector(mainContentSelector);

    // Use cached mainContent
    if (!mainContent) {
      return matches;
    }

    // Scan the main content of the page on initial page load.
    // Scan the context (if in main content) on subsequent behavior runs.
    let scanRoot;
    if (mainContent.contains(context)) {
      scanRoot = context;
    }
    else if (context.contains(mainContent)) {
      scanRoot = mainContent;
    }
    else {
      return matches; // Exit if there's no relationship between context and main-content
    }

    // Collect text nodes that pass shouldAcceptNode
    const walker = document.createTreeWalker(
      scanRoot,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode: shouldAcceptNode
      }
    );

    // Iterate over nodes to find useages of  terms.
    let node = walker.nextNode();
    while (node) {
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
            searchRegex
          });

          // Avoid searching for this term again.
          searchRegexes.delete(searchString);
        }
      }
      node = walker.nextNode();
    }

    return matches;
  }

  /**
   * Create HTML content for a tooltip by combining definitions with glossary source links.
   * @param {Object.<string, string>} definitions - Object mapping glossary UUIDs to definition text
   * @return {string} HTML string containing definition text and source citation links
   */
  function createTooltipContent(definitions) {
    return Object.entries(definitions).map(([uuid, definition]) => {
      return definition;
    }).join('<hr/>');
  }

  /**
   * Generate a unique ID for a tooltip.
   * @return {string} A unique ID for a tooltip.
   */
  function generateTooltipId() {
    return 'tooltip_' + Math.random().toString(36).substring(2, 11);
  }

  /**
   * Create the markup for a tooltip.
   * @param {string} text - The text to display in the tooltip.
   * @param {string} definition - The definition of the tooltip.
   * @return {string} The markup for a tooltip.
   */
  function createTooltip(text, definition) {
    const tooltipId = generateTooltipId();
    const element = template.content.cloneNode(true);

    const trigger = element.querySelector('.popover__trigger');
    const dialog = element.querySelector('.popover__dialog');
    const labelledby = dialog.getAttribute('aria-labelledby');
    const title = dialog.querySelector('.popover__title');
    const body = dialog.querySelector('.popover__body');

    trigger.textContent = text;
    body.innerHTML = definition;

    dialog.id = dialog.id.replace('uniqueID', tooltipId);
    dialog.setAttribute('aria-labelledby', labelledby.replace('uniqueID', tooltipId));
    title.id = title.id.replace('uniqueID', tooltipId);

    return element;
  }

  /**
   * Highlight matches in the text.
   * @param {Array<{node: Node, searchRegex: RegExp, searchString: string}>} matches - Array of matches containing text nodes, search regex and search string
   * @return {void}
   */
  function highlightMatches(matches) {
    // Group matches by their parent node to process them together
    const matchesByNode = new Map();

    matches.forEach(match => {
      if (!matchesByNode.has(match.node)) {
        matchesByNode.set(match.node, []);
      }
      matchesByNode.get(match.node).push(match);
    });

    matchesByNode.forEach((nodeMatches, node) => {
      const parent = node.parentElement;
      if (!parent) {
        return;
      }

      let text = node.textContent;
      let currentNode = node;

      // Create a set to track processed search strings for THIS node only
      const processedSearchStrings = new Set();

      // Find all matches and their positions first
      const matchPositions = [];

      nodeMatches.forEach(({searchRegex, searchString}) => {
        // Skip if this search string has already been processed for this node
        if (processedSearchStrings.has(searchString)) {
          return;
        }

        // Reset regex lastIndex
        searchRegex.lastIndex = 0;

        const match = searchRegex.exec(text);
        if (match) {
          matchPositions.push({
            start: match.index,
            end: match.index + match[0].length,
            matchText: match[0],
            searchString: searchString
          });

          // Mark this search string as processed for this node only
          processedSearchStrings.add(searchString);
        }
      });

      // Sort match positions by their start index (descending)
      matchPositions.sort((a, b) => b.start - a.start);

      // Process matches from right to left to avoid position shifts
      matchPositions.forEach(({start, end, matchText, searchString}) => {
        // Create text nodes for before and after the match
        const beforeText = document.createTextNode(text.substring(0, start));
        const afterText = document.createTextNode(text.substring(end));

        // Create the tooltip
        const definition = createTooltipContent(terms[searchString]);
        const tooltip = createTooltip(matchText, definition);

        // Replace the original text node
        parent.insertBefore(beforeText, currentNode);
        parent.insertBefore(tooltip, currentNode);
        parent.insertBefore(afterText, currentNode);
        parent.removeChild(currentNode);

        // Update the text for the next iteration
        text = text.substring(0, start);
        currentNode = beforeText;
      });
    });
  }
})(Drupal, drupalSettings);
