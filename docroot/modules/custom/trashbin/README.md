# Permanent purge and redirects

`drush trashbin:purge node` and `drush trashbin:purge media` preserve redirects
before permanently deleting an entity. No additional contributed module is
required.

This complements DP-49254: while a page is unpublished or trashed, incoming
redirects stay attached to it so republishing can restore their original
behavior. Redirect transfer happens only inside the permanent purge command.
For nodes, the replacement on the public alias (without `---unpublished`) is
considered along with explicit redirects on the system path and stored aliases.
Media page and `/download` replacements are resolved separately.

The command resolves affected redirects to their final destination, updates
their destinations without replacing their IDs, sources, languages, or status
codes, and creates redirects for system URLs and aliases with a known
replacement. It preserves destination queries/fragments and query passthrough.
Redirect changes and entity deletion share one database transaction.

If an incoming redirect has no onward replacement, a loop, an invalid local
target, conflicting replacements, or query/language-dependent routing that
cannot safely be transferred, the entity is skipped with a warning. Other
candidates continue. Review those warnings: a repeatedly skipped entity may
continue to occupy the command's oldest-first candidate window.

`drush --simulate trashbin:purge node` performs the same redirect checks and
reports proposed redirect updates and URL bridges without saving them.
These protections apply to this command; other entity deletion entry points
continue to use their existing deletion hooks.
