<?php

/**
 * Append to Changelog.md, based on yml files in /changelogs.
 *
 * Used by the release_branch job in CircleCI.
 */

use DrupalCodeGenerator\Application;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;
use PHLAK\SemVer;
use Twig\Loader\FilesystemLoader as FileSystemLoader;

require dirname(__DIR__) . '/vendor/autoload.php';

// Check for Holidays and changelogs files before cutting the release branch.
$date = new DateTime();
$todayDate = $date->format('m-d');
echo "Today date is: " . $todayDate;
echo "\n";
echo "";

// These are the only holidays that would not fall on Monday.
// Except for Thanksgiving which falls on 4th Thursday in November.
$holidayDates = array('01-01', '07-04', '11-14', '12-25');

// Iterate over Changelog files
$path = Path::join(dirname(__DIR__), 'changelogs');
$finder = Finder::create()
  ->in($path)
  ->name('*.yml')
  ->notName('template.yml');

foreach($finder as $file);

// Checks to see if today is holiday or no changelogs available.
if (in_array($todayDate, $holidayDates, true) || empty($file) ) {
  exit("There will be no release today. Because nothing to release or today is a holiday.");
  echo "\n";
  echo "";
}
else {
  echo("The release branch will continue.");
  echo "\n";
  echo "";
}

// Find the most recent tag in GitHub and used for the Changelog version as well.
$version = new SemVer\Version(`git describe --abbrev=0 --tags`);

// Increment the minor version by 1.
$version->incrementMinor();

// Display new version/tag.
echo "Display new version for release:" . $version;
echo "\n";
echo "";

// Creates the release branch with new version.
$release_branch = exec('git checkout -b release/' . $version);

// Display the new release branch name.
echo "This is the name for new release branch:" . "release/" . $version;
echo "\n";
echo "";

// Update the changelog.md with the changelog files.
$changes = [];

foreach($finder as $file) {
  $data = Yaml::parseFile($file->getPathname());
  foreach ($data as $type => $items) {
    if (isset($items['description'])) {
      $changes[$type][] = [
        'issue' => $items['issue'],
        'description' => $items['description'],
      ];
    }
    else {
      foreach ($items as $item) {
        $changes[$type][] = [
          'issue' => $item['issue'],
          'description' => $item['description'],
        ];
      }
    }
  }
  // Remove the old changelog files from the release branch.
  unlink($file);
}

// Display what going on with the changelog.md updates and removing old changelog files.
echo "Going through all of the changelog files to update CHANGELOG.md and removing old changelog files.";
echo "\n";
echo "";

$template_loader = new FileSystemLoader();
$template_loader->addPath(__DIR__);
$env = new \Twig\Environment($template_loader);
$context = [
  'changes' => $changes,
  'version' => $version,
  'release_date' => date('F j, Y'),
];

// var_dump($context);

$markdown = $env->render('changelog.twig', $context);

// Add the changes in Changelog.md to text file for GitHub release post.
// Each run of the release branch script will override the text file.
$textFile = fopen('scripts/changelog-body.txt','w');
fwrite($textFile,$markdown);
fclose($textFile);

$markdown .= file_get_contents(Path::join(dirname(__DIR__), 'CHANGELOG.md'));

// print $markdown;

file_put_contents(Path::join(dirname(__DIR__), 'CHANGELOG.md'), $markdown);

echo "Updated the CHANGELOG.md with changelog files.";
echo "\n";
echo "";

// Git add the changelog.md changes and removing the old changelog files from the release branch.
exec('git add .');

echo "Adding the CHANGELOG.md update and removing the old changelog files to release branch.";
echo "\n";
echo "";

// Git commit the changelog.md changes and removing the changelog files from the release branch.
exec('git commit -m "changelog update and remove old changelog files"');

echo "Commit the changes to release branch.";
echo "\n";
echo "";

// Git push the release branch up to GitHub.
exec('git push --set-upstream origin release/' . $version);

echo "Pushing those changes to release branch up to GitHub.";
echo "\n";
echo "";

// Create a Pull Request in GitHub against master branch for release

// Grab the changelog.md changes from the text file to use for Pull Request
$body = file_get_contents('scripts/changelog-body.txt');

// Get cURL resource
$ch = curl_init();

$data = array("title" => "Release " . $version, "body" => $body, "head" => "release/" . $version, "base" => "master");
$data_string = json_encode($data);

curl_setopt($ch, CURLOPT_USERNAME, 'massgov-bot');
curl_setopt($ch, CURLOPT_PASSWORD, getenv('GITHUB_MASSGOV_BOT_TOKEN'));
curl_setopt($ch, CURLOPT_POST, '-X');
curl_setopt($ch, CURLOPT_USERAGENT, 'https://api.github.com/repos/massgov/openmass/');
curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/massgov/openmass/pulls');
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

// Send the request
curl_exec($ch);

// Close request to clear up some resources
curl_close($ch);
