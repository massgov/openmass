#Pre-migration checklist
- Ensure you have a backup before running on Prod.


#Show starting status. Re-run this as desired to see status.
drush migrate:status

#This is the long migration that creates info_details nodes with all paragraphs.
drush migrate:import service_details

# Watches
drush migrate:import flaggings

# Migrate old redirects and add redirects from old service details alias to new node.
drush migrate:import update_redirects --update
drush migrate:import insert_redirects

#Delete all service details nodes.
drush entity:delete node --bundle=service_details

#Regenerate sitemap (optional - we can just wait for next run)
drush simple-sitemap:rebuild-queue
#This is very slow.
drush simple-sitemap:generate
