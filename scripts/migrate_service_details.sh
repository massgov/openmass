#Pre-migration checklist
# - Ensure you have a backup before running on Prod.

# Disable entity hierarchy.
drush sset entity_hierarchy_disable_writes 1 && drush cr

#Show migration status. Re-run anytime to see the current status.
drush migrate:status

#This is the long migration that creates info_details nodes.
drush migrate:import service_details

# Disable again because it got enabled by migrate:import post hook
drush sset entity_hierarchy_disable_writes 1 && drush cr

# After service details migration make sure that the new usage data is populated before running the other ones.
drush queue:run entity_usage_tracker

#This updates entity refs and link fields and other usages of the old service_detail nodes
drush migrate:import update_references_node

# Disable again because it got enabled by migrate:import post hook
drush sset entity_hierarchy_disable_writes 1 && drush cr

#This updates entity refs and link fields and other usages of the old service_detail paragraphs
drush migrate:import update_references_paragraph

# Disable again because it got enabled by migrate:import post hook
drush sset entity_hierarchy_disable_writes 1 && drush cr

# Migrate old redirects and add redirects from old service details alias to new node.
drush migrate:import update_redirects

# Disable again because it got enabled by migrate:import post hook
drush sset entity_hierarchy_disable_writes 1 && drush cr

# Insert redirects from old node path aliases to new info details node. Uses node table.
drush migrate:import insert_redirects

# Disable again because it got enabled by migrate:import post hook
drush sset entity_hierarchy_disable_writes 1 && drush cr

# Watches
drush migrate:import flaggings

# Disable again because it got enabled by migrate:import post hook
drush sset entity_hierarchy_disable_writes 1 && drush cr

#Delete all service details nodes.
drush entity:delete node --bundle=service_details

# Re-enable entity hierarchy
drush sset entity_hierarchy_disable_writes 0 && drush cr

# Entity usage updates are queued up. Just let cron process them.
# or manually run the command below
# drush queue:run entity_usage_tracker

# We need to rebuild the entire hierarchy tree.
drush entity-hierarchy-rebuild-tree field_primary_parent node

#Regenerate sitemap (optional - we can just wait for next run)
drush simple-sitemap:rebuild-queue
#This is very slow.
drush simple-sitemap:generate
