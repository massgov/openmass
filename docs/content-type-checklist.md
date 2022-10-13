# Content Type configuration checklist

## A list of configuration options, conventions, and other settings for Mass.gov content types.

### General

- Content type name
  - Be as concise as possible without sacrificing clarity
  - Omit the word “page” whenever possible (using the word "page" promotes an outdated understanding of content chunks as "pages" which we want to move users away from).
  - Sentence case: we have been inconsistent on this. Check with the content team about the current best practice (e.g. whether to use Decision Tree or Decision tree)

### Main "Edit" tab of the form

IMPORTANT: You will need input from both the content and the design teams to fully fill out this form.

- **Use this content type for...** -- Add whatever the content team provides. It should be in the form of a <ul>. It's optional, so may be left empty.
- **Link to live example** -- Not yet implemented. Links can be added to content that demonstrates how the page type will look.
- **Category** -- Ask the content team which category to add. If a new category is needed or edits to an existing one are requested, make that change in the `mass_admin_pages` module.
- **Thumbnail** -- Ask the design team to provide a thumbnail image. These are usually SVGs. The file will also need to be committed, to /modules/custom/mass_admin_pages/images/<filename>
- **Description** -- This is the main description for the content type. Add whatever the content team provides.
- **Vertical tabs**
  - Submission form settings
    _ Rename the "title" field if warranted
    _ Leave "Preview before submitting" as "optional."
    _ Skip submission guidelines for the content type (we don't currently do this with any regularity if at all)
    _ Add help text for the title field if provided (ask the Content team)
  - Publishing options
    - Leave default as Published, and Yes Create new revision.
  - Display settings
    - UNCHECK “display author and date info” (this really doesn't matter, but for consistency we have turned it off everywhere)
  - Menu settings
    - UNCHECK all menus (ditto)
  - Rabbit Hole settings
    - UNCHECK “allow overrides” unless you have a reason to allow overrides, and leave the default at “Display this page." (We use Rabbit Hole purely to keep non-useful (and generally poorly formatted) pages from public view.)
  - Scheduler
    - Enable scheduling of publish and unpublish for all content types unless you have a good reason not to. Enable "create new revision" for both.
  - Dependency options
    - See documentation for the <a href="descendant-manager.md">Descendant Manager</a> to learn more about how to configure any fields that create links between content.

### Fields

**THIS IS SECTION OUTLINES CURRENT PRACTICE BUT IS UNDER REVIEW**

- **Reuse** -- Re-use fields across content types unless there's a good reason to not to.
  - the "Organization Stakeholder" field, which is reused and should be added to every content type (see below).
- **Naming**
  - Include the content type name in the field, e.g. `field_content_type_name_of_field`
  - Include 'ref' in the machine name of entity references to note what’s being referenced: `field_how_to_ref_services`
- **Labels**
  - Use sentence case: only capitalize the first word.
  - Unless you have a very strong reason, use an entity reference field to a taxonomy term over a List (text) field type. _Changing list items is much more difficult, and no matter how certain you are the list won't change, you will be wrong most of the time_.
- **Multiple value fields**
  - To solve the terrible UI when you allow X number of a item (other than unlimited), we have a pattern of allowing an unlimited number of an item and then limiting it using javascript - try to use that.
- **Prefix/Suffix content**
  - Do add prefix or suffix content (e.g. "\$" or "No.") here - it's easier to adjust here than through changes at the theming level.
- **Document file fields**
  - Choose entity reference, content.
  - Type of content to reference = media.
  - Reference type bundles = document.
  - On the form display, change from autocomplete to inline entity form complex.
  - Entity browser = media browser.
  - All existing files.
- Things to add to all content types **except** "rabbit-holed" types that don't have standalone display:
  - Metatag
    - Add a field of type "metatag."
    - Save it (to make tokens available) and then open again to fill in any settings that differ from the defaults.
  - Org Stakeholders
    - Add an instance of the existing `

### Manage Form Display

- Order fields as they appear in the visual design unless there's a compelling reason not to.
- Disable Authored on, URL Alias, Promoted to Front Page, and Sticky at top of lists fields
- Paragraphs fields
  - Use Paragraphs Classic widget.
  - Open settings and add the correct singular and plural titles for each field.
  - Edit mode: generally leave as "Open," but consider whether Preview or Closed may be better suited.
  - Add mode: Use "Buttons" instead of "Dropdown button" (dropdown leaves choices too hidden).
  - Default type: generally, do choose the type of the paragraph to display an initial single empty item of that type.

### Moderation

- Enable moderation (unless there's some specific reason not to). **Note that the content type filter on the "all content" view our authors use requires content types to have moderation enabled to work, so it's important to turn it on!**
- Set the default state to "prepublished draft." **This is very important, as the Restricted Content Access tool depends on that prepublished state!**

### Link/Entity Reference combo Fields

- Add the new content type as an allowed type to reference on any field where needed.

### Config settings

- Sitemap XML: in the Sitemap entities tab of the config for this module, check off the content type to enable it, then check the configuration and Include it, and leave at the default priority. Unless it's a "rabbit-holed" content type that isn't ever displayed on its own - don't include those in the sitemap.
- URL alias: on the Pattern tab for the config, set the URL path pattern. (again, except for node types that don't ever stand alone - ignore those.)

### Permissions

- Normal permissions are: everything but “delete revisions” for all except Author role. Author role also doesn’t get “delete any” and “revert revisions.”
- Usually Content Admins, Devs and Testers get permissions when the type is created; once it's ready for full release, give Editor and Author permissions.
- Access unpublished: be sure to give permission to Anon user to enable use of this module (we want it enabled unless there's some specific reason not to)
- Remember to give Content Admins permission for new taxonomies, as needed
