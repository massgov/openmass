// The embedded_full_width image for file UUID
// e974845e-0c05-4922-9018-be0017198dd7, shared by all news items in captures.
const image = {
  path: '/files/styles/embedded_full_width/public/images/2026-11/familyusinglaptop_homepage_cropped.jpg?itok=WXoKykp5',
  alt: 'A happy family gathered around an open laptop.'
};
const items = [
  {
    title: 'Latest news from Massachusetts',
    summary: 'Find information about state programs, services, and resources.'
  },
  {
    title: 'Explore state programs and services for individuals and families',
    summary: 'Learn about programs available across Massachusetts and find information to help you and your family get the support you need.'
  },
  {
    title: 'Resources for your community',
    summary: 'Stay informed about services and opportunities in your community.'
  }
];

module.exports = async function normalizeHomepageNews(page) {
  await page.evaluate(({ image, items }) => {
    if (!document.body.classList.contains('is-front')) return;
    const section = document.getElementById('news--updates')?.closest('.ma__stacked-row__section');
    if (!section) throw new Error('Homepage News & updates section was not found');
    const columns = [...section.querySelectorAll('.ma__split-columns__container > .ma__split-columns__column')];
    if (!columns.length) throw new Error('Homepage News & updates has no news items');

    const textNodes = (root, exclude = () => false) => {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
      const nodes = [];
      while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.textContent.trim() && !exclude(node) &&
          !node.parentElement.closest('svg, script, style, [aria-hidden="true"], .ma__visually-hidden')) nodes.push(node);
      }
      return nodes;
    };
    // Validate every card before changing content. Retain the existing nodes,
    // including linked h3s, embedded-image wrappers and summary formatting.
    const cards = columns.map((column, index) => {
      const card = column.querySelector(':scope > .ma__rich-text');
      const heading = card?.querySelector(':scope > h3');
      const link = heading?.querySelector('a');
      const images = card?.querySelectorAll('img');
      const img = images?.[0];
      const wrapper = img?.closest('.embedded-entity');
      const title = link ? textNodes(link) : [];
      const summary = card && heading && wrapper ? textNodes(card, node => heading.contains(node) || wrapper.contains(node)) : [];
      if (images?.length !== 1 || !wrapper || !title.length || !summary.length) {
        throw new Error(`Homepage news item ${index + 1} is missing the expected embedded image, linked h3, or summary`);
      }
      if (img.hasAttribute('srcset') || img.closest('picture')) {
        throw new Error(`Homepage news item ${index + 1} has a new responsive image structure; update its capture fixture`);
      }
      return { img, title, summary };
    });
    // Distribute the fixture across existing text nodes so inline links and
    // emphasis still render text and remain part of the visual comparison.
    const replaceText = (nodes, value) => {
      const words = value.split(' ');
      nodes.forEach((node, index) => {
        const from = Math.floor(index * words.length / nodes.length);
        const to = Math.floor((index + 1) * words.length / nodes.length);
        const text = words.slice(from, to).join(' ') || 'Information';
        node.textContent = index < nodes.length - 1 ? `${text} ` : text;
      });
    };
    cards.forEach((card, index) => {
      const item = items[index % items.length];
      card.img.src = new URL(image.path, location.href).href;
      card.img.alt = image.alt;
      replaceText(card.title, item.title);
      replaceText(card.summary, item.summary);
    });
  }, { image, items });
};
