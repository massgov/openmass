#Pre-migration checklist
# - Ensure you have a backup before running on Prod.


#Show starting status. Re-run this as desired to see status.
drush migrate:status

#This is the long migration that creates info_details nodes.
drush migrate:import service_details

#This updates entity refs and link fields and other usages of the old service_detail nodes
drush migrate:import update_references

# Insert redirects from old node path aliases to new info details node. Uses node table.
drush migrate:import insert_redirects

# Watches
drush migrate:import flaggings

# Migrate old redirects and add redirects from old service details alias to new node.
drush migrate:import update_redirects

#Delete all service details nodes.
drush entity:delete node --bundle=service_details

#Regenerate sitemap (optional - we can just wait for next run)
drush simple-sitemap:rebuild-queue
#This is very slow.
drush simple-sitemap:generate
