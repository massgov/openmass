# Remote video with Able Player

The existing **Video** media type (`media_video`) keeps its Video Embed Field
source and stored YouTube/Vimeo URLs. No media migration is required.

Its default display uses **Able Player Remote Video** (`mass_media_ableplayer`).
Configure it at `/admin/structure/media/manage/media_video/display`.
Editors continue to add or edit Video media using the existing **Video URL**
field and transcript fields.

The custom formatter adapts Video Embed Field URLs to the Able Player library
provided by the `ableplayer` module. It handles unlisted Vimeo privacy hashes,
legacy YouTube `/watch/ID` URLs, and whitespace in existing values. A Drupal
behavior initializes each player once, including media inserted through AJAX.
The provider must allow embedding. Playback uses provider captions unless local
caption tracks are uploaded on the Video media item.

## Optional accessibility content

Expand **Video accessibility** on the normal Video add/edit form:

- **Captions and subtitles:** upload one `.vtt` file per language. After each
  upload, select its language and optionally enter the label shown in the player.
  Files can be reordered; the first caption file is the default. Uploaded tracks
  replace provider captions in this player.
- **Offer an interactive transcript:** enabled by default. When caption files
  are uploaded, the Transcript control opens a synchronized, clickable transcript.
  This setting does not change the existing transcript text or transcript link.
- **Chapters:** upload a `.vtt` file with chapter names and time ranges, then
  select its language. The player adds a Chapters menu.
- **Audio descriptions (.vtt):** upload timed descriptions of visual information,
  with one file per language. Match each track's language to its captions. The
  player can speak the descriptions using browser speech synthesis, with a
  screen-reader fallback, and include them in the interactive transcript.
  Viewers can adjust the voice, speech settings, and automatic pausing in
  Preferences. Leave **Audio-described video URL** empty to use spoken WebVTT
  descriptions. If both formats are supplied, Able Player 5.0 uses the alternate
  video for description playback; the WebVTT text still contributes to the
  interactive transcript.
- **Audio-described video URL:** link to a version with narration of important
  visual information, hosted on the same provider as the main video. Matching
  timing lets the viewer switch versions with the Description control.
- **Synchronized sign-language video URL:** link to a YouTube interpretation
  video whose timing matches the main YouTube video. The viewer can show a
  synchronized interpretation window. Able Player 5.0 does not support this for
  Vimeo main videos or on iOS; incompatible provider combinations are rejected
  by entity validation.

The uploads are managed files with a 2 MB limit and normal file usage tracking.
The `mass_media_video_track` field type extends Drupal's File field to store
each file's language and optional label. All new fields are optional and hidden
from separate field output; the player formatter consumes them and carries file
access results and cache dependencies into its render array.

Caption, chapter, and description files need WebVTT timestamps, for example:

```text
WEBVTT

00:00.000 --> 00:05.000
Welcome to the video.
```

For chapters, replace the cue text with a chapter title. For spoken descriptions,
describe the important visual information at that point in the video. The player
synchronizes and can speak supplied description text; editors provide the text
and any recorded narration or interpretation videos.

Mayflower's local video atom override renders the formatter's render array so
its JavaScript and CSS attachments reach the page. It retains the video heading,
skip links, position, and transcript link, and avoids Mayflower's iframe sizing
script around Able Player controls. The fallback iframe remains available for
video data supplied without a configured player, including the client-rendered
decision tree component.

The exported configuration includes Able Player and the Media Library dependency
installed with it. A Composer patch prevents Able Player 3.4.3 from creating
incomplete display records for a missing media bundle named `video`. Its installer
also leaves configuration imports in control of media fields and displays.

`mass_media_update_10001()` removes the two malformed installer records from
environments that already attempted an installation, using raw configuration
deletion because Drupal cannot instantiate the broken display entities. Valid
display records are preserved. This update runs before configuration import in
the normal `drush deploy` / `ddev updatedb` workflow, so a failed preview can be
rebuilt with the fix without manually editing its database.

Validation:

```sh
ddev exec vendor/bin/phpunit docroot/modules/custom/mass_media/tests/src/Unit/AblePlayerInstallTest.php
ddev exec vendor/bin/phpunit docroot/modules/custom/mass_media/tests/src/ExistingSite/AblePlayerVideoTest.php
ddev exec vendor/bin/phpunit docroot/modules/custom/mass_media/tests/src/ExistingSite/VideoAccessibilityTest.php
```
