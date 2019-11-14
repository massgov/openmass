#!/usr/bin/env bash

restore() {
	status=$(drush ms | grep 'mass_moderation_migration_restore:node');
	status_array=( $status );

	if [ "${status_array[6]}" == "0" ]
	then
    	echo "Restore migration complete";
	else
      remaining="$((${status_array[4]} - ${status_array[5]}))"
      percent="$((${status_array[5]} * 100 / ${status_array[4]}))";
    	echo "${percent}% complete: ${status_array[5]} items processed.";
      echo "${remaining} items remaining.";
      echo "Resuming migration...";
    	drush mmm:restore;
    	restore;
	fi
}

restore;
