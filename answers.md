# BCM Developer Test: written answers

This file is the longer write-up for each question. Setup and how to run things live in **README.md**. The whole **bcm-test** folder can sit under your WordPress site root (for example `htdocs/wordpress/bcm-test`) so Q1 runs in the browser; Q9 runs from the terminal; Q10 loads as a must-use plugin.

**Layout:** `q1/` fixed listing + `problem.php` (original bugs), `q4/`–`q6/` static HTML/CSS/JS, `q9/` signup logic, `q10/` REST endpoint. Landing pages: `q9/index.html`, `q10/index.html`.

---

## Q1 PHP: search listing with pagination and `[ad]`

**Files:** `q1/test.php` (or `q1/index.php`), `q1/bcm-helpers.php`, `q1/problem.php` (broken brief, for comparison)


| #   | Error in the original                                                                                     | Effect (PHP 8 / WordPress)                                                                                   | Fix                                                                                           |
| --- | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------- |
| 1   | Snippet starts with `$page = ...` and no `<?php`                                                          | If that is the real file start, the code prints as text                                                      | Added `<?php` (assumed excerpt)                                                               |
| 2   | `WP_Query([...])` without `new`                                                                           | Fatal: `Call to undefined function WP_Query()` (run)                                                         | `new WP_Query`                                                                                |
| 3   | `'page' => $page`                                                                                         | Not a pagination arg for this query, so every URL shows posts 1-10                                           | `'paged' => $page`                                                                            |
| 4   | `$_GET['page']` used raw, echoed with `<?= $page ?>`                                                      | `?page=abc`, `-1`, arrays give odd queries; echoing raw input is reflected XSS                               | `bcm_page_param()`: integer >= 1, anything else becomes 1                                     |
| 5   | `$_GET['s']` read with no default                                                                         | Missing `s`: `Warning: Undefined array key "s"` (run). WordPress also slashes `$_GET`                        | `bcm_search_param()`: default `''`, `wp_unslash`, `sanitize_text_field`; `esc_html` on output |
| 6   | `$pages` never set                                                                                        | Warning and heading reads "Page 1 of :"                                                                      | `max(1, $q->max_num_pages)`                                                                   |
| 7   | `<h1>` never closed                                                                                       | Everything after it sits inside the heading                                                                  | Closed                                                                                        |
| 8   | Pattern `#[ad]#`                                                                                          | Unescaped brackets make a character class: it matches every `a` and `d` (8 matches in `a bad dad [ad]`, run) | `#\[ad\]#`                                                                                    |
| 9   | `preg_replace_callback` has no subject argument                                                           | `ArgumentCountError` on PHP 8 (run)                                                                          | Pass `$content`                                                                               |
| 10  | Callback uses `$m[1]` but declares no `$m` and the pattern has no group; it `echo`es instead of returning | Undefined variable warning; ad divs print above the page and the content is not changed                      | Callback returns the markup; uses a counter                                                   |
| 11  | `get_the_content()` alone                                                                                 | No blocks, shortcodes or `wpautop`                                                                           | `apply_filters('the_content', ...)` first, then swap markers                                  |
| 12  | `esc_html(the_title())`                                                                                   | `the_title()` echoes by itself and returns null, so the title prints unescaped and `esc_html` gets nothing   | `esc_html(get_the_title())`                                                                   |
| 13  | `<p><?php echo $content ?></p>`                                                                           | Post content is block-level HTML; a `<p>` around it is invalid and browsers add empty paragraphs             | `<div class="entry-content">`                                                                 |
| 14  | No `wp_reset_postdata()`, no pagination links, no "no results" state                                      | Later template code sees the wrong post; users cannot reach page 2; empty page looks broken                  | Added all three                                                                               |


**Assumptions about the ad placeholders**

- `[ad]` is a literal text marker in post content, and no shortcode called `ad` exists.
- Each marker becomes `<div class="ad ad-N" data-ad-slot="N"></div>`. N counts up across the whole page, so slots are unique even with several posts.
- A marker alone in a paragraph replaces the whole `<p>`, so we never emit `<p><div>` (invalid).
- The JS ad handler finds `.ad` / `data-ad-slot`. PHP never outputs ad scripts.

**Checks on WordPress:** open `/bcm-test/q1/test.php` with `WP_DEBUG` on. Try no query args, `?page=2`, `?s=word`, `?s=zzzzzz`, `?page=abc`, and posts that contain literal `[ad]` (and text like "bad dad" without markers). Compare with `/bcm-test/q1/problem.php` to see the original faults. Pagination and search should work with no new entries in `wp-content/debug.log`.

---



## Q2 WordPress admin: editor cannot upload images, colleague can

Since a colleague can upload on the same site, server-wide causes (disk full, folder permissions) are unlikely. The difference is the **person, the file, or the browser/network**.


| Order | Likely cause                                                                                                                                                                                        | Evidence to look for                                                                                                                  | What to do                                                                                                              |
| ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| 0     | (Cheap split first) Reproduce and capture the exact message plus the HTTP status of the upload request in DevTools > Network. Then: colleague uploads the editor's file, editor uploads a tiny PNG. | Fails only for the file: it is file-related. Fails only for the editor with a tiny PNG: account/browser.                              | Narrows the branch                                                                                                      |
| 1     | The editor's account lacks `upload_files` (custom role, role-manager plugin, or the account is really a Contributor)                                                                                | "Sorry, you are not allowed to upload files", Media menu missing, 403. `wp user get <id> --fields=roles` and `wp user list-caps <id>` | Restore the capability in the role manager. Do not make them Administrator. Re-test with the editor and note the change |
| 2     | The file: too large for `upload_max_filesize` / `post_max_size`, or a type WordPress blocks (HEIC, some SVG/WebP setups)                                                                            | "exceeds the maximum upload size" or "file type not permitted"; 413; the colleague fails with the same file                           | Resize/convert. If the type is needed, allow it deliberately (`upload_mimes`). Raise PHP limits only if justified       |
| 3     | Security layer (WAF or security plugin) blocking this user, IP or file                                                                                                                              | A 403 page that is not WordPress's, with a rule ID in the WAF log; works from another network                                         | Add a narrow exception for that rule, not a blanket allow                                                               |


---



## Q3 WordPress: slow article page on mobile

1. **Full-page and CDN caching for anonymous readers.**
2. **Images** responsive  and size , including other media files like videos
3. **Third-party scripts (ads, analytics, tag manager, embeds):** load async/defer, delay what is not needed for first render, drop unused tags. Measure first: main-thread time and TBT/INP per script in a DevTools performance trace, plus the request waterfall.

**Chosen: caching (1).** How to prove it helped without breaking anything:

- **Baseline:** 7 days of field data (CrUX or RUM, mobile p75 TTFB and LCP) plus 5 lab runs (WebPageTest/Lighthouse, mobile throttling) on 3 article URLs.
- **Rollout:** staging first, then a subset of paths. Cache bypass for `wordpress_logged_in_`*,* `wp-postpass_`, comment cookies, previews, `?s=`, POST, REST and admin-ajax. Tracking parameters are stripped from the cache key only.
- **Ads:** the `.ad` placeholders are present in the cached HTML; ad requests fire (Network tab); impressions and RPM within a few percent of the same weekday last week.
- **Analytics:** Realtime shows the visit; tag preview shows events fire; sessions and key events within normal variation vs last week.
- **Signed-in:** log in as an editor: admin bar present, drafts and previews are not cached, nobody gets another user's page (check `Cache-Control: private`, `X-Cache: BYPASS`).
- **Freshness:** edit an article and confirm it updates within the agreed time (see Q7).
- **Success:** p75 TTFB and LCP clearly lower, hit ratio high, ads/analytics unchanged, zero signed-in issues. **Rollback:** switch the cache rule off.

---



## Q4 CSS layout: `q4/index.html`, `q4/style.css`

Add desktop/mobile screenshots next to the HTML if your submission requires them. Section blocks use classes like `q4-block-a` (not single-letter IDs in CSS).

- **Breakpoint:** 48em (768px), mobile first.
- **Technique:** one grid, no JavaScript, no duplicated sections. Block A spans 2 of 3 columns. On mobile `order` gives A D B C G; E, F, H are `display:none`. Long heading in block D uses `overflow-wrap:anywhere` and `min-width:0` on grid items.
- **Keyboard and screen readers:** HTML order is desktop reading order (A through H). On mobile, visual order differs from tab order; hidden blocks are `display:none` so they leave the tab order. `reading-flow: grid-order` helps where supported.

---



## Q5 HTML: `q5/index.html`

**Actual HTML5 validity errors** (W3C Nu validator, `evidence/html-validation-original.txt`):

- no doctype; `<body>` placed after `</html>` (stray body)
- `name` on `<abbr>` (not allowed; used `title`)
- `with=300` (typo, not an attribute; should be `width`)
- `height=auto` (must be an integer)
- `<img>` without `alt`
- `</code></b>` mis-nested (should close `</b></code>`)

**Valid but worth improving (accessibility/style):** `lang` on `<html>`; `<meta charset>` and viewport; quoted attribute values (`href=https://...` is valid unquoted, but fragile); stray spaces inside the link text and in `</a >`; `type="button"` on the button; `<main>`/`<article>` landmarks.

**Search discoverability/interpretation (HTML only):**

1. A descriptive `<title>` and `<meta name="description">` instead of "Example".
2. `lang="en"` plus `<main>`/`<article>` with a single `<h1>`, so crawlers and assistive tech can see the main content.
3. Meaningful `alt` text, plus `width`/`height` on the image (stops layout shift, which affects page experience).
4. `<abbr title="...">` so the abbreviation has a machine-readable expansion.

**Validation:** run the W3C Nu checker on `q4/index.html`, `q5/index.html`, and `q6/index.html`. Place `test.jpeg` beside `q5/` and `q6/` for the image to load.

---



## Q6 JavaScript: `q6/index.html`, `q6/script.js`, `q6/test.js`

Built on the corrected Q5 markup; `index.html` includes a small inline layout style and centred buttons.

- **Add button:** clones the three original paragraphs (saved on load), shuffles them, inserts after the last paragraph.
- **Separators:** rebuild `<hr data-separator>` after every pair when another paragraph follows; runs on load and after each click. Hidden paragraphs still count.
- **Toggle button:** added in JS; toggles `hidden` on paragraphs with an image; new clones follow the current state.
- **Checks:** from `q6/`, run `npm i jsdom && node test.js` (19 checks). Open `index.html` in a browser to click through manually.
- **Note:** hidden paragraphs still count toward pairs, so you can see adjacent `<hr>`s when image blocks are hidden next to each other.

---



## Q7 Stale article after a theme release

**Assumptions:** a CDN or page cache sits in front of WordPress; the correction was saved and published; the release ran through the normal pipeline; no service worker.

**First three checks**

1. **What do different readers actually get?** `curl -sI` the URL, with and without a cache-busting query string and from two locations. Read `Age`, `Cache-Control`, `ETag`/`Last-Modified`, `X-Cache`/`CF-Cache-Status`.
2. **What does the origin serve?** Request the origin directly (bypassing the CDN) and check the post in wp-admin: is the correction the current published revision, with the expected `post_modified`?
3. **Is every server on the same release?** Compare theme version / asset `?ver=` / a release marker across origin nodes.

**Telling cache from code/content**


| What you see                                                                                                      | Likely                    |
| ----------------------------------------------------------------------------------------------------------------- | ------------------------- |
| Old copy has a growing `Age`; the cache-busted URL or a purge fixes it; differs by location or edge               | Cache                     |
| Origin itself serves the old text on some requests, or nodes disagree on release                                  | Deploy/code problem       |
| Origin serves old text everywhere and wp-admin shows the correction as draft/pending/scheduled or on another post | Content problem           |
| Only one browser/device shows old text; a hard reload fixes it                                                    | Browser or service worker |


**Roll back when** the release itself is broken (errors, broken ads, wrong templates) or nodes cannot be made consistent quickly. **Do not** roll back for plain cache staleness: it will not fix it. Purge the article URL, and the listing pages that show it, instead. A second release without a diagnosis adds risk and may not change anything.

**Verify recovery:** the same `curl` from several locations shows the new text and a reset `Age`; the version marker matches on all nodes; error rate and ad/analytics requests normal; spot-check logged-in and anonymous; watch for an hour as caches expire.

**Update to the editor:** "The corrected article is saved on our servers, but some readers are still being shown an older cached copy, and I'm clearing that cache for the article now. I'll confirm in about 30 minutes that it shows the update from several locations, and we'll only need another release if I find the cause is in the code."

---



## Q8 Initiative: copy and paste between tools

**Ask or observe first:** sit with one editor through a real publish (or record one). Which tools get which values (title, link, image)? Where have mistakes happened (wrong link, old image, missing tracking tags)? How long does it take? Which values change per tool?

**Smallest useful improvement (about one engineering day):** a read-only "Publishing kit" box in the post editor sidebar. It shows the saved title, the permalink (plus a tracking-tagged variant if they use one), and the featured image URL with its alt text, each with a Copy button, and one "Copy all" block. It is built from the saved post, so it cannot go stale, and it needs only a small meta box and a clipboard script.

**Simpler alternative (little or no new code):** an existing automation (Zapier/Make, or the WordPress trigger "post published") that posts title, URL and image link into one place the editors already use (a Slack channel or a sheet row), plus a short checklist. Or just a shared template. This is worth trying first if the editors already work from one tool.

**Why this choice:** it removes retyping and wrong-version errors without changing how editors decide anything.

**Stays under editorial control:** when to publish and share, which channels, wording of social text, which image. The tool only displays and copies; it posts nothing.

**Test with one editor:** a 30-minute session on 3 real articles, before and after. Time each article, count errors, and ask them to think aloud.

**Success:** measured time per article down (agree a target, e.g. 30%, from the baseline), no wrong-link or wrong-image errors over two weeks, and the editor still uses it when no one is watching.

**Similar improvement I made (about 90 words). DRAFT: replace the [brackets] with your real facts; I only know the project, not the outcome.**

> At Ise Owo, our artisan marketplace, I built the Trusted Contact consent and concern-reporting flow in Laravel. [What was manual or error-prone before.] I used a two-token design (a one-time consent token and a persistent concern token), queued jobs and mailables for sending, and kept logic in services rather than controllers so each step could be tested. [Outcome: e.g. steps removed, time saved, errors avoided.] I learned to automate only the repeated, error-prone step, keep a person in charge of the decision, and test with a real user before adding features.

---



## Q9 APIs and data reliability: `q9/subscribe.php`, `q9/test.php`, `q9/index.html`

One function, `subscribe()`, plus a fake API in `test.php`. This is **CLI-only** (not a WordPress page). See `q9/index.html` for a short pointer. Run from the `q9` folder: `php test.php` (13 PASS).

- **Timeout:** the outcome is unknown, so we never assume failure. Before every retry after the first, `find()` asks the service whether it already has the address; if so we are done. If not, we call `create()` again with the **same idempotency key**, stored before the first request, so the service replays instead of duplicating.
- **Rate limit (429):** wait for `Retry-After`, otherwise exponential backoff with jitter; then retry.
- **Invalid email:** validated locally first, so no API call and no retry. A 400/422 from the service is also final (`failed`).
- **Never lost:** out of attempts means status `pending`, with the record and key kept, for a background job to call `subscribe()` again.
- **Store:** normalised email, status, idempotency key, attempt count, timestamps (a DB table in production).
- **Log:** event name, attempt number, HTTP status, masked email (`j***@example.com`).
- **Avoid logging:** full email addresses, API keys/authorization headers, the idempotency key, request/response bodies.
- **Retry after the service already accepted:** `FakeApi` stores the record and then throws a timeout. Tests 1 and 2 (service without idempotency support; and with lagging lookup plus key) both end with exactly one record. The baseline test shows the naive version creating two.

---



## Q10 Security: "the button is only visible to editors"

**The claim is wrong.** Hiding a button is a usability choice, not access control. Anyone who is logged in (or anyone at all, if the route is open) can send the same request with `curl` or DevTools. The server must decide every time.

**How to run:** copy `q10/endpoint.php` to `wp-content/mu-plugins/` on the same WordPress install as this repo. Opening the PHP file in the browser does nothing useful; see `q10/index.html`. Route: `POST /wp-json/bcm/v1/articles/{id}/status`.

**Required checks, in order** (`q10/endpoint.php`):

```
1. authenticate        - valid login; cookie requests need a valid X-WP-Nonce (CSRF)   -> 401/403
2. validate input      - id is an integer > 0; status is in the allowlist (draft|pending|publish) -> 400
3. load the object     - post exists AND post_type == 'post'                           -> 403 (do not reveal which IDs exist)
4. authorize on object - current_user_can('edit_post', id)   (not just "is an editor")  -> 403
5. authorize on status - if status == publish: current_user_can('publish_post', id)    -> 403
6. transition rule     - is old -> new allowed? (no jumping from trash/private/future)  -> 409
7. act                 - wp_update_post(...), check for WP_Error
8. audit               - log user, post, old -> new
```

**Two requests that must be rejected**

1. A Contributor (can edit own drafts, cannot publish) sends `POST /wp-json/bcm/v1/articles/<own draft id>/status {"status":"publish"}` with a valid nonce. Expect 403 and the post status unchanged in the database.
2. A request with no login or no valid nonce (e.g. from another site, or `curl` without cookies) for any post. Expect 401/403 and no change. Add: `status=trash` or a made-up value gives 400; the ID of a page or someone else's post gives 403.

**Raising it with the developer:** keep it about the code, not the person. Show a 3-line `curl` reproduction using a low-privilege account, and say plainly: "UI visibility is not a permission check, so as written any logged-in user can publish." Link the WordPress handbook on `permission_callback` and capability checks, offer the fixed version above, and offer to pair on the tests.

**Must be fixed before release:** the server-side checks above, tests for the two rejected cases (and one success), an audit log line, and a quick search for other endpoints trusting the UI.