# Mass CKEditor 5 Enhancements

This module provides hierarchical numbering for nested ordered lists with legal-style formatting.

## Features

- **Legal-Style List Numbering**: Automatically numbers nested ordered lists using hierarchical numbering (e.g., 1.1, 1.2, 1.2.1, 1.2.2, 1.3)
- **Text Filter**: Processes HTML content to add numbering spans to list items
- **CKEditor Integration**: Provides CSS styling for proper display in both CKEditor and the front-end

## Installation

1. Enable the module using Drush:
   ```bash
   drush en mass_ckeditor5 -y
   ```

2. Clear the cache:
   ```bash
   drush cr
   ```

3. Configure the text format and enable the filter:
   - Go to Configuration → Text formats and editors
   - Edit the desired text format (e.g., Full HTML)
   - Enable the "Legal-Style List Numbering" filter
   - Save the configuration

4. Add the Legal style to CKEditor (optional but recommended):
   - In the same text format configuration
   - Find the "CKEditor settings" → "Styles" dropdown configuration
   - Add: `ol.list-style-legal|Legal`
   - This allows users to apply the Legal style via the Styles dropdown instead of editing source code

## Usage

### Configuration

To enable the Legal style option in CKEditor:

1. Go to **Configuration → Text formats and editors**
2. Edit your desired text format (e.g., Full HTML)
3. In the "CKEditor settings" section, find the "Styles" dropdown configuration
4. Add the following style class definition:
   ```
   ol.list-style-legal|Legal
   ```
5. Save the configuration

### Creating Legal-Style Lists

To create a hierarchical numbered list in CKEditor:

1. Create an ordered list using the list button in CKEditor
2. Click on the ordered list to select it
3. Use the "Styles" dropdown in the toolbar
4. Select "Legal" from the available styles
5. The list will immediately show hierarchical numbering (1., 1.1., 1.2., etc.)

Example Input HTML:
```html
<ol class="list-style-legal">
  <li>First item
    <ol>
      <li>Nested item 1.1</li>
      <li>Nested item 1.2</li>
    </ol>
  </li>
  <li>Second item</li>
</ol>
```

Example Output HTML (after filter processing):
```html
<ol class="list-style-legal" style="list-style-type:none;">
  <li>
    <span class="multi-level-list__marker">1. </span>First item
    <ol class="multi-level-list legal-list" style="list-style-type:none;">
      <li><span class="multi-level-list__marker">1.1. </span>Nested item 1.1</li>
      <li><span class="multi-level-list__marker">1.2. </span>Nested item 1.2</li>
    </ol>
  </li>
  <li><span class="multi-level-list__marker">2. </span>Second item</li>
</ol>
```

This will display as:
```
1. First item
1.1 Nested item 1.1
1.2 Nested item 1.2
2. Second item
```

## Technical Details

### Filter Plugin

The `FilterLegalListNumbering` filter plugin:
- Searches for `<ol class="list-style-legal">` elements
- Recursively processes ALL nested `<ol>` elements (regardless of class)
- Adds `<span class="multi-level-list__marker">` elements with calculated hierarchical numbers to each `<li>`
- Adds `class="multi-level-list legal-list"` to all nested `<ol>` elements
- Adds `style="list-style-type:none;"` inline style to all `<ol>` elements
- Supports unlimited levels of nesting
- Attaches the CSS library only when legal-style lists are found in the content

### CSS Styling

The module includes two separate CSS files:

**CKEditor CSS** (`css/legal-list-ckeditor.css`):
- Uses CSS counters (`counter-reset`, `counter-increment`, `counters()`) for live preview
- Shows hierarchical numbering while editing in CKEditor
- Handles CKEditor's `ck-list-bogus-paragraph` spans
- Loaded in CKEditor 5 editor via `ckeditor5-stylesheets` in info.yml
- Loaded in CKEditor 4 editor via `hook_ckeditor_css_alter()`

**Front-end CSS** (`css/legal-list.css`):
- Styles the filter-generated `multi-level-list__marker` spans
- Works with the processed HTML structure (after filter runs)
- Applies proper indentation for nested lists (2em padding-left)
- Loaded on front-end pages via filter plugin library attachment
- Only loaded when content contains `list-style-legal` lists

### How It Works

**In CKEditor (editing mode):**
1. User adds `class="list-style-legal"` to an `<ol>` element
2. CSS counters show hierarchical numbering (1., 1.1., 1.2., etc.) in real-time
3. The source HTML remains clean (no filter-generated spans in editor)

**On Front-End (display mode):**
1. Filter plugin detects `<ol class="list-style-legal">` elements
2. Processes all nested `<ol>` elements recursively
3. Adds `<span class="multi-level-list__marker">` with calculated numbers
4. Adds classes and inline styles to nested lists
5. Attaches front-end CSS library
6. Displays hierarchical numbering using the generated spans

## Customization

To modify the numbering format or styling:
1. Edit `css/legal-list.css` to change the visual appearance on a front end.
2. Edit `css/legal-list-ckeditor.css` to change the visual appearance in CKEditor.
3. Modify `src/Plugin/Filter/FilterLegalListNumbering.php` to change the numbering logic
