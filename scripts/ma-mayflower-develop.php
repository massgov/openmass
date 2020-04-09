<?php

/**
 * Use the Mayflower develop branch as way to test beforehand the integration with Mass repository.
 */


require dirname(__DIR__) . '/vendor/autoload.php';

// Give some data information about when the process was done.
// Allows us the ability to look at the Mayflower develop branch changes.
$display = new DateTime();
$display->setTimezone(new DateTimeZone('America/New_York'));
echo "Display the date and time (EST): " . $display->format("m-d-Y h:i:s A\n");


echo "----------------------------------------------\n";
echo "\n";

// Using the date as a way to identify the branch in Mass Repository GitHub.
$date = new DateTime();
$date->setTimezone(new DateTimeZone('America/New_York'));
$branch_name = $date->format("m-d-Y\n");

// Creates the release branch with new version.
exec('git checkout -b mayflower-dev-' . $branch_name);
echo "Create a new branch: " . "mayflower-dev-" . $branch_name . "\n";

echo "----------------------------------------------\n";
echo "\n";

// The following line will update the mayflower-artifacts in the commposer.json
// and composer.lock files for the branch. While using the Mayflower artifacts
// the results will be whatever branches in the Mayflower repository that
// are merged by this variable $display will be included.
exec('composer require massgov/mayflower-artifacts:dev-develop --update-with-dependencies');

echo "The following command is being used 'composer require massgov/mayflower-artifacts:dev-develop --update-with-dependencies'.\n";
echo "\n";
echo "----------------------------------------------\n";
echo "\n";

// Following commands will add changes and commit with a message below.
exec('git add .');

echo "Track the files by using git add.\n";
echo "\n";
echo "----------------------------------------------\n";
echo "\n";

exec('git commit -m "Update the composer.json and lock file with the Mayflower develop branch"');

echo "Update the composer.json and lock file with the Mayflower develop branch.\n";
echo "\n";
echo "----------------------------------------------\n";
echo "\n";

// The following line will push up to GitHub.
exec('git push --set-upstream origin mayflower-dev-' . $branch_name);

echo "Pushed the branch up to GitHub to be deployed to an environment.\n";
echo "\n";
echo "----------------------------------------------\n";
echo "\n";

