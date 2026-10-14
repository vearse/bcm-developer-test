# BCM Developer Test

Hi. This folder is my complete submission for the BCM software developer test. **answers.md** has the full written answers; this README is the quick start. You can drop the whole `bcm-test` directory into an existing WordPress install (for example MAMP `htdocs/wordpress/bcm-test`) and run most of the exercises from there without hunting for paths. Static pieces (`q4`, `q5`, `q6`) work by opening the HTML in a browser or serving them under the same host.

I used documentation and some AI help while drafting, then I re-ran the scripts, clicked through the pages, and fixed anything that did not match what I expected.

## Quick map


| Folder | What it is                                                                                     |
| ------ | ---------------------------------------------------------------------------------------------- |
| `q1/`  | Fixed listing (`index.php`, `bcm-helpers.php`) and the original broken snippet (`problem.php`) |
| `q4/`  | Responsive A–H grid, one HTML + one CSS                                                        |
| `q5/`  | Corrected HTML (`index.html`) and the broken source (`problem.html`)                           |
| `q6/`  | Q5 markup plus `script.js`                                                                     |
| `q9/`  | Newsletter signup logic (`subscribe.php`, `test.php`, `index.html`)                            |
| `q10/` | REST endpoint plugin (`endpoint.php`, `index.html`)                                            |




## Run it on WordPress (MAMP or similar)

1. Copy or clone this folder to `wp-content/../bcm-test` (sibling to `wp-load.php`), or keep it where it already lives under your site root.
2. Start Apache and MySQL. Open the site once so WordPress is installed.
3. **Q1:** Visit `/bcm-test/q1/test.php`. Try `?page=2`, `?s=word`, `?s=zzzzzz`, `?page=abc`. Compare with `/bcm-test/q1/problem.php` to see the original faults. Put `[ad]` in a few posts to see ad slots. Turn on `WP_DEBUG` and `WP_DEBUG_LOG` in `wp-config.php` if you want a log file.
4. **Q9 / Q10:** Open `q9/index.html` or `q10/index.html` in the browser for a one-paragraph how-to, or see **answers.md**.
5. **Q10:** Copy `q10/endpoint.php` into `wp-content/mu-plugins/` (create the folder if needed). Log in as different roles and hit `POST /wp-json/bcm/v1/articles/{id}/status` with and without a nonce.

---



## Q1 PHP listing, pagination, search, and `[ad]`

The brief gave a short template with several PHP 8 and WordPress mistakes. I listed what each mistake does in my own notes (missing `new`, wrong query arg for pagination, bad regex, callback that echoes instead of returning, undefined `$pages`, and so on). The working version is in `q1/test.php` with helpers in `bcm-helpers.php`.

I assume `[ad]` is plain text in the post body, not a shortcode. Each marker becomes a `<div class="ad ad-N">` with a slot number that continues across posts on the same page. A lone `[ad]` in its own paragraph replaces the whole `<p>` so we do not nest a div inside a paragraph.

---



## Q2 Editor cannot upload images

A colleague uploading on the same site usually means the server is fine and the difference is the person, the file, or the session. I would reproduce once with DevTools open, then swap file and user (editor uploads a tiny PNG, colleague uploads the editor’s file). After that I would check role capabilities (`upload_files`), file size and type limits, nonce or cached admin JS, then security plugins or WAF rules. I would fix the narrowest thing that matches the evidence, not blanket admin access.

---



## Q3 Slow article on mobile

I would look at three areas: caching and TTFB for anonymous readers, image weight and LCP, and third-party scripts (ads, analytics). For each I would measure first (field data, Lighthouse or WebPageTest, Query Monitor on origin). I would probably prove caching first with cache bypass for logged-in cookies, then confirm ads and analytics still fire and editors still see uncached previews.

---



## Q4 CSS grid A–H

`q4/index.html` and `q4/style.css` implement the desktop and mobile layouts with one grid, breakpoint at `48em`, mobile reorder with `order`, and E/F/H hidden on small screens. HTML order is desktop reading order, so on mobile keyboard focus does not always match visual order; hidden blocks use `display: none` so they leave the tab order. Add your own screenshots beside the HTML if the brief asks for them.

---



## Q5 HTML fixes

`q5/problem.html` is the broken brief. `q5/index.html` fixes validity errors (doctype, body placement, `abbr` title, image attributes, nesting) and adds a few search-friendly tags (`lang`, description meta, landmarks, real `alt` text). Put a `test.jpeg` next to the HTML for the image to load.

---



## Q6 JavaScript behaviour

Built on the Q5 page. The first button clones the three original paragraphs in random order after the last one. Separators are rebuilt on load and after every click so pairs get an `<hr>` when another paragraph follows; hidden paragraphs still count. A second button is created in JS and toggles paragraphs that contain an image. `q6/test.js` clicks through repeated adds and toggle combinations.

---



## Q7 Stale article after a release

I would compare what different readers get (`curl -I`, cache headers, cache-bust query), what the origin returns versus wp-admin, and whether all nodes run the same theme build. Growing `Age` and a purge fixing it points to cache; origin wrong everywhere points to content or deploy. I would not ship another release just to flush cache. Roll back only if the release itself is broken. Short editor message: the fix is saved, some edges still serve an old copy, we are purging and will confirm within about thirty minutes.

---



## Q8 Copy/paste between tools

I would shadow one editor through a real publish and note which fields go to which tool and where mistakes happen. Smallest build I like: a sidebar “publishing kit” with title, permalink, image URL, and copy buttons from the saved post. Cheaper option: Zapier or a Slack post on publish plus a checklist. Editors still choose when and where to publish; we only remove retyping. Success is less time per article and fewer wrong links over two weeks. (Replace the personal paragraph in your cover note with your own project story if you submit one.)

---



## Q9 Newsletter signup, timeouts, and duplicates

Code: `q9/subscribe.php`. Run `php q9/test.php`.

When the HTTP call times out we do not know if the signup landed. I keep a local row per email before the first request, with a stable idempotency key sent on every `create`. After a timeout I call `find` before creating again so a slow success does not turn into a duplicate. Invalid emails fail locally with no retry. `429` respects `Retry-After`, otherwise backoff with jitter. If we run out of attempts the row stays `pending` for a later job.

I store normalised email, status, key, and attempt count. I log event names, HTTP codes, and masked email. I avoid full addresses, API keys, idempotency keys, and raw bodies in logs. The tests include a case where the service accepted the signup but the client only saw a timeout, then a later retry with the same key still ends with one subscriber.

---



## Q10 Endpoint security review

Hiding a button in the admin does not protect the route. Anyone logged in, or anyone if the route is open, can replay the request. Server must authenticate, validate input, load the post, check `edit_post` and `publish_post` on that post, allow only sensible status transitions, then update and log.

`q10/endpoint.php` is my stricter version for WordPress REST. Two cases that must fail: a Contributor posting `publish` on their own draft (403, status unchanged), and a request with no valid login or nonce (401/403, no change).

I would show the developer a short `curl` as a Contributor, say UI visibility is not authorization, point at `permission_callback` and capability checks, and ask for tests before release.

---



## AI use

I used AI during test, documentation  and structuring answers. I verified by running on wordpress directtly, loading the HTML, and reading through `problem.php` versus `index.php` line by line.