Backstop Visual Regression Testing
==================================

Visual regression tests are run manually before and after release. Before
release, we capture reference screenshots from production, then compare those
screenshots to the staging environment.

Following release, we smoke test by checking the same production URLS to ensure
they look the same.

> **Note:**  This test will not catch any functional test steps or Javascript
> changes. The following items would not be tested by Backstop: dropdown,
> accordion, table of contents, or menu within a page. These need to be manually
> checked to see if any visual regression has happened to these items. (e.g.
> organization subnav dropdown, mobile view of the menu, info details, table of
> contents, or contact accordion.)
>
> We are expecting to see changes in some instances. Pages may change from the
> "reference" screenshots based on either code level changes or content level
> changes. It's up to you to use your judgment on whether these changes are
> expected/acceptable or not.

## Options

```
--target=local  # Choose local, test (stage), prod (default), feature#, or tugboat.
--list=all      # The supported scenario list (default).
--viewport=all  # Default viewports: all (default) or desktop.

--cachebuster   # Appends a cache busting query string to URLs of pages to be tested.
```

Scenarios are defined in `all.json`. Individual scenarios can override the
default viewports through their `screens` or `viewports` settings.
`--cachebuster` is off by default. It adds a random query parameter to page URLs;
image and stylesheet URLs keep their normal cache behavior.

## Usage

- For protected environments (`test`, `feature1`, etc.), add the HTTP Basic
  credentials used by the browser login to the gitignored `.ddev/.env` file:

  ```dotenv
  LOWER_ENVIR_AUTH_USER="your-http-username"
  LOWER_ENVIR_AUTH_PASS="your-http-password"
  ```

  `ddev backstop` reads this file on each run; no restart is required. Existing
  nonempty container environment values take precedence. These credentials are
  separate from SSH keys configured by `ddev auth ssh`. Production references do not
  require these credentials. If reference generation succeeded and the test
  stopped on missing credentials, keep those references and rerun only the test.
- BackstopJS runs in DDEV's web container using the versions pinned in
  `package.json` and `yarn.lock`. Run `ddev yarn install --frozen-lockfile`
  before the first test. The `ddev backstop` command downloads the matching
  Chromium build once and keeps it in DDEV's persistent global cache.
- Before doing any testing, you will need to capture the "reference" screenshots,
  or the screenshots you want to use as the baseline for comparison. These
  reference screenshots will usually come from the production environment. Take
  screen captures of production pages with
  `ddev backstop reference --target=prod --list=all`
  Reference screenshots can also be captured from a Tugboat environment with
  `ddev backstop reference --target=tugboat --viewport=desktop --tugboat=https://your-preview.tugboat.qa/`
- Take screen captures of local pages to compare
  `ddev backstop test --target=local --list=all`
- Open the report from the comparison
  `ddev backstop-results`

## Modifying Tests

You can change the pages that are captured by editing the `all.json` file in
this directory. We _do not_ want to test every page on the site, as this type of
testing is very slow, but it is good to have one or two "representative samples"
of all the different page variations on the site.

## Running Backstop in CircleCI

CircleCI installs the versions of BackstopJS and Playwright pinned in
`package.json` and `yarn.lock`, downloads the matching Chromium build, and runs
Backstop directly in the pinned `cimg/node:24.22.1-browsers` executor. Local
runs use the same project dependencies and Chromium build inside DDEV's web
container instead of maintaining a separate Backstop image.

`drush ma:ci:backstop-snapshot` will run Backstop and store the screenshots to be
used later by `drush ma:ci:backstop-compare` e.g.
```
drush ma:ci:backstop-snapshot --target=prod
drush ma:ci:backstop-compare --reference=prod --target=test
```

If you want to run a specific branch you're working on to test changes, you can
use the `--ci-branch` parameter.
```
drush ma:ci:backstop-snapshot --target=prod --ci-branch=your-branch
```

To create new references and test them in the same job, you can force new reference
images to be created by adding `--force-reference` e.g. to test changes you've been
making to backstop related code:
```
drush ma:ci:backstop-compare --reference=prod --target=test --force-reference --ci-branch=your-branch
```

With `--force-reference`, references come from `--reference` and test screenshots
come from `--target` (`test` means stage.mass.gov). These references stay within
the comparison job and do not update the shared production baseline.

## Capture readiness and diagnostics

Reference and test captures use the same readiness checks. Each page must return
its configured `expectedStatus` (200 by default; the intentional 404 scenario
sets 404). Warmup and actual browser navigation retry transient failures up to
three times. Unexpected 4xx responses and persistent server errors fail capture.

`ready.js` has a shared 90-second deadline (`captureTimeout`, in milliseconds,
can be overridden per scenario). Within that deadline it checks active linked
stylesheets, visits each viewport to trigger lazy content, returns to the top,
and waits for image decoding, fonts, Tableau readiness, and one second of stable
image geometry and page height. CSS background images and pseudo-elements are
included. Image crop styles are preserved. Downloaded documents are excluded
from visual scenarios because Chromium's PDF viewer is not reliable here.

Responsive images load and settle using their original markup at each test
viewport. For the full-page screenshot only, the capture hook holds `picture`
source media queries to their current match results, then restores the original
queries in a `finally` block. Chromium can briefly resize the viewport to
1 × 1 inside screenshot capture, selecting a low-resolution mobile fallback
after readiness has already passed. This browser workaround preserves the real
selected source, crop, and layout; image URLs, `srcset`, `sizes`, and resolution
descriptors stay intact. It does not substitute a predetermined image or take
an extra screenshot. Viewport-only screenshots are unaffected. This addresses
media-based picture sources; width-based candidate selection within a `srcset`
is still handled by the browser.
Readiness diagnostics retain both the selected `source` and original
`fallbackSource`.

For the Homepage scenario, a browser initialization hook limits
`homepage_background_images` to the first image alphabetically by site-relative
desktop image path, ignoring the hostname and query string. The site's existing
script renders that entry's desktop/mobile image and real credits. Capture waits
for the background and matching credit markup, then checks image decoding as
usual. Diagnostics record the selected path and URLs. Removing an entry selects
the next available image automatically; different image lists or credit metadata
between environments can still produce real content differences. Review and
regenerate references when the selected banner changes.

Homepage News & updates keeps its real card markup, linked headings, image
wrappers, formatting, and responsive CSS. Only headline/summary text and image
URLs/alt text are normalized. Every card uses the `embedded_full_width` family
image for file UUID `e974845e-0c05-4922-9018-be0017198dd7` on the target host.
Fixed headlines and summaries of different lengths exercise text wrapping.
The expected rich-text card structure is validated before normalization;
missing images, linked headings, or summaries fail at the `homepage news`
step. The replacement image must pass the normal image readiness checks.
Other homepage rich text and all non-homepage content are left intact.
Refresh homepage references when adopting this change from the gray boxes.

On pages with a JavaScript-generated table of contents, capture waits for every
expected heading link and jump target. Collapsed mobile links are checked too.
Pages below the component's minimum section count must finish initialization
and hide the TOC. TOC links and geometry are included in the final stability
check and diagnostics; an unfinished TOC fails at the `table of contents` step
within the shared deadline.

Horizontal sticky navigation stays in normal flow below its banner during
capture, including after lazy-loading scrolls. Its geometry is included in the
layout stability check so it cannot float over the page content.

Tableau containers expose `data-tableau-state` as `loading`, `ready`, `error`, or
`timeout`. The test hooks also observe public Tableau readiness callbacks/events
on environments running older application code. A missing spinner is never used
as proof that a visualization is ready.

After Tableau becomes interactive, Backstop captures each visible visualization
iframe while it is in view and overlays that bitmap for the final full-page
screenshot. This avoids Chromium omitting offscreen cross-origin charts from
full-page captures. The original iframe stays in the layout and the viewport
size is preserved. This only changes the test browser; it does not change the
site or replace Tableau with sample data. Diagnostics include a snapshot count
for each Tableau container.

Regular content iframes (`.js-ma-responsive-iframe` / `.ma__iframe__container`)
must render nonempty content before capture. Successful responses and rendered
HTTP 404 error pages are captured. Backstop waits for their document, images,
fonts, and visible busy indicators, then preserves the rendered frame using
the same capture method. A 404 page appears as received inside the full-page
screenshot, with its HTTP status and `error-page` state retained in diagnostics.
Other HTTP errors, network failures, and empty frames remain readiness failures.
Intentionally blocked embeds remain excluded.

Gravity Forms embeds (`.js-gf-iframe`) must show initialized fields with no
visible loading spinner, and the iframe must resize to fit the form before its
bitmap is captured. Diagnostics include `formState`. Snapshot alignment is
measured after bitmap decoding so embed-triggered scrolling cannot move the
form over the page header.

Vimeo and YouTube iframe areas receive the same fixed Video placeholder in
reference and test captures, preserving the original dimensions. Before masking
each frame, a separate check waits up to five seconds for visible play/pause
controls without clicking Play. It records `playerCheck: rendered` or
`playerCheck: unverified` in diagnostics and logs, and saves a player screenshot
when controls render. Provider HTTP errors or timeouts produce warnings while
visual comparison continues. Media requests to video providers are blocked.
Placeholders follow iframe resizing while keeping the original page layout.
This verifies player UI separately from video playback and keeps comparisons
independent of provider challenges, poster changes, and playback. Regenerate
references with this behavior before comparing.

CircleCI runs a representative page and stylesheet preflight before reference
and test jobs, requiring two consecutive successful checks. Run it locally with:

```bash
ddev exec node backstop/scripts/preflight.js --target=local
```

Each capture writes a JSON file under `backstop/diagnostics/` with document
status, failed requests, image URLs and dimensions, background crop styles,
and Tableau states. Readiness failures also save a diagnostic screenshot and
appear as engine errors in Backstop rather than ordinary visual differences.
Intentionally blocked third-party requests are excluded from failed-request
logs. Credentials and sensitive URL query parameters are redacted. CircleCI
stores diagnostics as artifacts, including when readiness fails.

Run the controlled Chromium checks with:

```bash
ddev exec node --test backstop/scripts/readiness.test.js
```

These cover delayed and lazy images, responsive image changes, stable repeated
captures at desktop/mobile sizes, failed CSS/images, HTTP status handling, and
both Tableau API generations. After changing capture behavior, review and
regenerate reference images before using them for a release comparison.
