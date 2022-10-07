Tracking via queue
============
The module support tracking entity usage via a queue. When the entity usage is
tracked via a queue, the tracking information will be updated when cron runs.
This means some references between entities could be missing. Only use this setting
when you are sure there are no automatic processes using the tracking information
to update or delete content.

Since this is for advanced users only, this setting is not exposed in the UI.
This can be enabled through the settings.php by adding the following line:

$config['entity_usage_clean_table.settings']['queue_tracking'] = TRUE;

You also need to periodically run `drush clean_usage_table` to clean references
to outdated revisions.
