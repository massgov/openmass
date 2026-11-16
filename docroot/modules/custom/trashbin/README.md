# Permanent purge and redirects

`drush trashbin:purge node` and `drush trashbin:purge media` preserve working
redirects and clean up broken redirects when permanently deleting an entity.
No additional contributed module is required.

This complements DP-49254: while a page is unpublished or trashed, incoming
redirects stay attached to it so republishing can restore their original
behavior. Redirect transfer happens only inside the permanent purge command.
An explicit redirect for the destination path takes precedence, followed by
its unaliased system path. Other addresses for the content, including a node's
public alias without `---unpublished`, are fallbacks only when they resolve to
one destination. Media page and `/download` replacements are resolved separately.

The command validates affected chains but rewrites only references to paths
being deleted. A healthy surviving chain keeps its intermediate destinations
and handles its own queries, fragments, and language. Redirect IDs, sources,
languages, and status codes are retained. Redirects for system URLs and aliases
with a known replacement are still created, so direct links to those former
addresses continue to work as well as existing friendly redirect chains.
Redirect updates, removals, and entity deletion share one database transaction.
Existing unpublished target pages remain valid destinations: they may be
published later. External HTTP/HTTPS destinations are not fetched or checked.

When Pathauto changes a node's alias to add `---unpublished`, Redirect can
automatically create an old-public-alias redirect back to that same node. If
there is no onward replacement, that direct, query-free record does not block
purging: it is retained unchanged after deletion hooks run, without creating
additional redirects to the deleted node. An explicit replacement still takes
precedence. This exception preserves the record, not the deleted content.

Redirect problems do not block permanent deletion. Affected redirects without
an onward replacement, with loops, or with missing local targets are removed.
Failed downstream branches and upstream links stranded by removals are checked
independently; healthy chains do not need another cleanup traversal. Separate
working redirects and surviving local routes are retained. This is scoped to
the purged entity's redirect chains, not a site-wide redirect cleanup.

Different source paths may intentionally have different working destinations;
each keeps its own mapping. If an incoming redirect needs a replacement for a
deleted path and the fallback choices conflict or depend on query/language,
only that ambiguous incoming redirect is removed. No replacement is chosen
by creation date, and no bridge is created with conflicting destinations.
Conflict checks compare validated final targets, so different intermediate
paths reaching the same target do not make a friendly URL ambiguous.
Query/language-dependent routing through a surviving path is retained without
flattening it. Warnings explain removals and ambiguous bridges.

Concurrent edits between planning and deletion still skip that entity so an
editor's changes cannot be overwritten. A save or deletion failure rolls back
the whole transaction.

`drush --simulate trashbin:purge node` performs the same planning and reports
proposed redirect updates, retained records, removals, and URL bridges without
saving them or deleting content.
These protections apply to this command; other entity deletion entry points
continue to use their existing deletion hooks.
