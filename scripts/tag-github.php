<?php

/**
 * To create a new tag for github either a hotfix (patch) or release (minor)
 *
 * Used by the tag_release_github job in CircleCI.
 */

use PHLAK\SemVer;

require dirname(__DIR__) . '/vendor/autoload.php';

// This will grab the commit subject line from the last commit to master branch.
$branch = exec('git log -1 --pretty=%s');

echo "Last commit subject message:" . " " . $branch;
echo "\n";
echo "";

// Find the most recent tag in GitHub master branch to update.
$version = new SemVer\Version(`git describe --abbrev=0 --tags`);

echo "Find the last tag created:" . " " . $version;
echo "\n";
echo "";

// The tag version will always be major.minor.patch (e.g. 0.235.0)

// If the commit message has the word "release" in it the tag will increment as minor.
if(strpos($branch, "release") !== false){
  $version->incrementMinor();
}
// If the commit message has the word "hotfix" in it the tag will increment as patch.
elseif (strpos($branch, "hotfix") !==false){
  $version->incrementPatch();
}
// If none of those words are found the tag will be unable to increment correctly.
else {
  exit( "Unable to increment the Semantic version for the tag.");
}

// Display the increment tag from the conditional statement above.
echo "Here is the new tag" . " " . $version;
echo "\n";
echo "";

// Create a Release in GitHub against master branch. Github will create a corresponding tag.

// Grab the body from the changelog-body.txt which is created by the build-chagnelog.php
$markdown = file_get_contents('scripts/changelog-body.txt');

// Get cURL resource
$ch = curl_init();

// Create the tag release with all data
$data = array("tag_name" => $tag, "target_commitish" => "master", "name" => $tag, "body" => $markdown, "draft" => false, "prerelease" => false);
$data_string = json_encode($data);

curl_setopt($ch, CURLOPT_USERNAME, 'massgov-bot');
curl_setopt($ch, CURLOPT_PASSWORD, getenv('GITHUB_MASSGOV_BOT_TOKEN'));
curl_setopt($ch, CURLOPT_POST, '-X');
curl_setopt($ch, CURLOPT_USERAGENT, 'https://api.github.com/repos/massgov/openmass/');
curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/massgov/openmass/releases');
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

// Send the request
curl_exec($ch);

// Close request to clear up some resources
curl_close($ch);
