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

echo "Last commit subject message:" . " " . $branch . "\n\n";

// Find the most recent tag in GitHub master branch to update.
$version = new SemVer\Version(`git describe --abbrev=0 --tags`);

echo "Find the last tag created:" . " " . $version . "\n\n";

// The tag version will always be major.minor.patch (e.g. 0.235.0)

// If the commit message has the word "release" in it the tag will increment as minor.
if(stripos($branch, "release") !== false){
  $version->incrementMinor();
}
// If the commit message has the word "hotfix" in it the tag will increment as patch.
elseif (stripos($branch, "hotfix") !== false){
  $version->incrementPatch();
}
// If none of those words are found the tag will be unable to increment correctly.
else {
  exit( "Unable to increment the Semantic version for the tag.");
}

// Display the increment tag from the conditional statement above.
echo "Here is the new tag " . $version . "\n\n";

// Grab the body for the GitHub release tag includes what is being deployed.
// The following line is looking to see if this is hotfix tag. If so it will take the last commit from today within the CHANGELOG.md.
// Note if a release happen the same day as hotfix it will take both changes from the CHANGELOG.md and post to the body.
// The last commit output is moved to the scripts/changelog-body.text to be used by the $markdown
if(strpos($branch, "hotfix") !== false){

  // Using git blame for today changes in the CHANGELOG.md and clean the output up before moving it to the scripts/changelog-body.txt.
  exec("git blame -s --since=today -- CHANGELOG.md | grep -v '^\^' | sed -e 's/00000000...//' -e 's/[0-9]..//' > scripts/changelog-body.txt");
  // Then grabbing the content from the changelog-body.txt. Reuse the content for the $markdown in the body.
  $markdown = file_get_contents('scripts/changelog-body.txt');

} else {
  // If this is a release just use the changelog-body.txt that was created from build-changelog.php script (created the release branch)
  $markdown = file_get_contents('scripts/changelog-body.txt');
}

// Create a Release in GitHub against master branch. Github will create a corresponding tag.
// Get cURL resource
$ch = curl_init();

// Create the tag release with all data
$data = ["tag_name" => (string)$version, "target_commitish" => "master", "name" => (string)$version, "body" => $markdown, "draft" => false, "prerelease" => false];
$data_string = json_encode($data);

curl_setopt($ch, CURLOPT_USERNAME, 'massgov-bot');
curl_setopt($ch, CURLOPT_PASSWORD, getenv('GITHUB_MASSGOV_BOT_TOKEN'));
curl_setopt($ch, CURLOPT_POST, '-X');
curl_setopt($ch, CURLOPT_USERAGENT, 'https://api.github.com/repos/massgov/openmass/');
curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/massgov/openmass/releases');
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

// Send the request
$return = curl_exec($ch);

// Close request to clear up some resources
curl_close($ch);

if (!$return) {
  exit(1);
}
