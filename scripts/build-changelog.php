<?php

/**
 * Append to Changelog.md, based on yml files in /changelogs.
 *
 * Used by the release_branch job in CircleCI.
 */

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;
use PHLAK\SemVer;

require dirname(__DIR__) . '/vendor/autoload.php';

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
$path = Path::join(dirname(__DIR__), 'changelogs');

// Iterate over Changelog files
$finder = Finder::create()
  ->in(__DIR__ . '/../changelogs')
  ->name('*.yml')
  ->notName('template.yml');

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

$env = new \Twig_Environment(new \Twig_Loader_Filesystem(__DIR__));
$context = [
  'changes' => $changes,
  'version' => $version,
  'release_date' => date('F j, Y'),
];

// var_dump($context);

$markdown = $env->render('changelog.twig', $context);

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

// Get cURL resource
$ch = curl_init();

$data = array("title" => "Release" . $version, "body" => "xxxx", "head" => "release/" . $version, "base" => "master");
$data_string = json_encode($data);

curl_setopt($ch, CURLOPT_USERNAME, 'massgov-bot');
curl_setopt($ch, CURLOPT_PASSWORD, $_ENV['GITHUB_MASSGOV_BOT_TOKEN']);
curl_setopt($ch, CURLOPT_POST, '-X');
curl_setopt($ch, CURLOPT_USERAGENT, 'https://api.github.com/repos/massgov/mass/');
curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/massgov/mass/pulls');
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

// Send the request
curl_exec($ch);

// Close request to clear up some resources
curl_close($ch);
