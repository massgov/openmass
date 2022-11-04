Tracking via queue
============
The Entity Usage Queue Tracking module adds support to Entity Usage for tracking via a queue. When the entity usage is tracked via a queue, the tracking information will be updated when cron runs. This means some references between entities could be missing. Only use this setting when you are sure there are no automatic processes using the tracking information to update or delete content.

Since this is for advanced users only, this setting is not exposed in the UI. This can be enabled through the settings.php file by adding the following line:

$config['mass_entity_usage.settings']['queue_tracking'] = TRUE;

You also need to periodically run `drush clean_usage_table` to clean references
to outdated revisions.
