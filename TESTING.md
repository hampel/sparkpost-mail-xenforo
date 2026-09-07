TESTING
=======

What this add-on touches, what breaks quietly, and who has to check it.

## Surfaces

**Code event listeners** — the three entry points; everything else hangs off them.

| Event | Callback | Why |
|---|---|---|
| `app_setup` | `Listener::appSetup` | binds `sparkpostmail.api` (the SubContainer) and `sparkpostmail.log` |
| `app_admin_setup` | `Listener::appAdminSetup` | registers the `sparkpostmail.test` factory for the ACP test page |
| `mailer_transport_setup` | `Listener::mailerTransportSetup` | substitutes the SparkPost transport when SparkPost is the selected transport |

**Class extensions** — seven, and all but one exist to mark mail transactional or not.

| Extended | Purpose |
|---|---|
| `XF\Mail\Mail` | swaps in `SparkPostEmail`; sets campaign id from the template name, user metadata, and the test-mode recipient rewrite |
| `XF\Mail\Mailer` | `applyMailDefaults` marks everything transactional, applies the tracking options |
| `XF\Job\UserEmail` | marks bulk user email non-transactional |
| `XF\Service\User\Welcome` | marks welcome email non-transactional |
| `XF\Admin\Controller\Tools` | adds the test page; marks its own test mail non-transactional |
| `XF\Admin\Controller\Option` | adds the SparkPost branch to the email transport setup flow |
| `Hampel\WhatsNewDigest\Job\SendDigest` | marks digests non-transactional — only loads if that add-on is installed |

**Template modifications** — two, both on the email transport options UI:
`option_email_transport_setup` (adds the SparkPost choice) and
`option_template_advancedEmailTransport` (swaps in this add-on's display when SparkPost is selected).

**Other artifacts**: one option (`sparkpostmailMessageEventsBatchSize`) and its group; an admin
navigation entry under Checks & Tests; three cron entries (fetch, process, prune); one code event
(`sparkpostmail_non_transactional_stop_map`); four admin/email templates; four `sparkpost:*` CLI
commands; and the `xf_sparkpost_mail_message_event` table created by `Setup.php`.

## Fragile points

- **The API key does not live in its own option.** It is stored inside XenForo's core
  `emailTransport` option value, alongside the tracking and test-mode flags. Nothing but
  `Option\EmailTransport` should read that structure. A core change to how that option is stored
  breaks this add-on silently — mail keeps sending until the transport is re-selected.
- **Template modifications against core option templates.** These are the things that break on an
  XF upgrade without any error: the modification simply stops matching and the SparkPost choice
  disappears from the options page. One such modification had already died this way, targeting a
  template a later XF removed entirely.
- **Unsubscribe routing keys off the mail template name.** `XF\Mail\Mail::setTemplate` sets it as
  the SparkPost campaign id, and `MessageEventService::processUnsubscribe` prefix-matches that
  against a stop map. A new mail type whose template name is not in the map falls through to the
  catch-all, which stops **all** of that user's email of that class. Adding a mail type means
  checking the map.
- **The fetch watermark is in the simpleCache, not the database.** A cache flush silently resets
  it, and the next run reaches back eleven days. There is a sixty-second minimum window width
  because SparkPost rejects narrower ones.
- **Logging is a soft dependency.** The logger must never resolve to null — everything that logs
  passes the container value straight into `setLogger(LoggerInterface)`. Covered by a regression
  test now, because it shipped broken once.
- **The add-on's `vendor/` is mostly shadowed by XenForo's own.** XF's autoloader registers first,
  so any package XF also ships resolves to XF's copy regardless of what this lock says. Composer
  constraints here do not govern what actually runs. Check versions against XF's `src/vendor`
  before assuming an upgrade takes effect.
- **Building the mail transport must not resolve the logger.** They are on a cycle through
  Hampel/Monolog's email handler, and closing it recurses until the stack is exhausted. 5.0.0
  shipped exactly that. `Log\LazyLogger` keeps the resolution deferred; `TransportLoggerCycleTest`
  pins it. **Neither half of the cycle exists on a development install** — `monologSendEmail` is
  off by default and a dev board rarely selects SparkPost — so this is a class of defect the
  suite can only catch by simulating it, never by encountering it.
- **A failed `composer install` cannot fail the build.** XF's release builder discards the exit
  status of `build.json` exec steps, so a broken install produces a release zip with no `vendor/`
  at all. `Setup::checkRequirements()` is the only thing standing between that and the user.

## Automated

```bash
composer install                  # first time; vendor/ is gitignored
vendor/bin/phpunit                # the unit suite
php cmd.php xf-dev:class-lint     # whole install, not add-on scoped - check the path on a failure
```

The suite is a starter set, not full coverage — see `CLAUDE.md` for what it covers and what is
still missing. When adding to it, mutation-check each assertion: break the behaviour the test
names and confirm that test fails.

Also settled without a human, by script:

- Every `_output/class_extensions/*.json` `to_class` resolves to a file under the add-on.
- Every template modification's `find` still matches its core template — worth running after any
  XenForo upgrade, since nothing else reports it.
- Every option key referenced in code or templates exists in `_output/options/`. Expect false
  positives from the legacy option ids `Setup::upgrade()` reads directly for the pre-2.0
  migration, and from the admin navigation section id.
- The release zip contains no `CLAUDE*`, `TESTING.md`, `phpunit.xml`, `tests/` or dev
  dependencies, and *does* contain `vendor/`. Build it and unzip; do not read the exec lines and
  assume.

### The upgrade path, in a disposable sandbox

**A session can settle this, and it should on every release.** A `Setup.php` step that works on a
fresh install and fails upgrading from the previous version is the classic failure here, and no
amount of testing the working copy finds it.

**Upgrade from the last *published* version, not the last tag.** They are not the same — this
add-on was built and deployed at 4.0.0 without ever reaching the resource page — and the published
version is the range users actually traverse.

**The target must not be a development install.** `AddOnActionTrait::importAddOnData()` asks
`isAddOnOutputAvailable()` *before* it looks at the release's data, and when the answer is yes it
runs `xf-dev:import` against the working copy instead. The run takes a different code path from
the one a user gets, completes cleanly, and reports nothing — so a green result says nothing about
the upgrade having been tested. `xenforo23.local` is exactly that install: its
`src/addons/Hampel/SparkPostMail` *is* the git working copy.

**A throwaway XenForo in Docker avoids the problem entirely** — `xenforo-addon-sandbox` mode 3.
The add-on arrives only as zips, so the install has no `_output/` and takes the real path
regardless of how development mode is configured. Install the last published release from
`~/releases/xenforo/Hampel-SparkPostMail/`, then upgrade from the zip just built. Under six
minutes end to end.

```bash
xf-cli xf:addon-install /releases/Hampel-SparkPostMail/Hampel-SparkPostMail-4.0.1.zip
xf-cli xf:addon-upgrade /var/www/html/_testzips/Hampel-SparkPostMail-5.0.0.zip
```

What to check, and in this order:

- **`Importing add-on data` in the output.** That single line is the whole difference between the
  user's path and the `xf-dev:import` shortcut. An `xf-dev:import` invocation means the run proved
  nothing.
- **The database synchronised.** It does, correctly — phrases, options, class extensions,
  listeners, cron entries and template modifications all match the working copy afterwards.
  `SELECT ... FROM xf_template_modification_log` is the cheapest check that the two modifications
  still match their core templates, and it is stronger than grepping the template source.
- **The filesystem did not, because an upgrade removes nothing.** XenForo's extractor writes the
  new zip's entries and never deletes, so every file the new version dropped is still there while
  a fresh install is clean. Verified on 4.0.1 → 5.0.0: `Api/SparkPostApi.php`, the four
  `Exception/*` classes and `vendor/hampel/symfonymailer-sparkpost` all survived. Inert that time
  — `xf-dev:class-lint` exited 0 — but inert is a property of that upgrade, not of upgrades. Lint
  the *upgraded install* rather than trusting the zip, since the zip is correct and the disk is
  not.
- **The error log and the pages.** `xf_error_log` empty, front page and `admin.php` both 200.

If no sandbox is available, say so rather than reporting the path as untested-but-fine, and fall
back to reading `Setup.php` for `upgrade*()` steps gated between the last published version and
this one. `Setup::upgrade()` gates its only step on `version_id < 2000000`, so nothing fires on any
path a current user takes.

## Needs a human

- **The email transport options page.** Whether the SparkPost radio option appears, whether
  selecting it reaches the API key form, and whether saving round-trips. This is the surface the
  template modifications own, and it is where an XF upgrade breaks things.
- **A real send through the ACP test page** (Checks & Tests → Test SparkPost Mail), against a live
  SparkPost account. Nothing offline proves the API key, the sending domain, or that a message
  actually leaves. Check both transactional and non-transactional, since they take different
  paths.
- **A failing send**, to confirm the error surfaces readably. SparkPost's own message and error
  code should appear in the dedicated fields, not just as raw JSON inside the generic error.
- **Bounce processing end to end.** Send to a SparkPost sink address that generates a bounce,
  then run the fetch and process commands and confirm the user's email state changed and the row
  landed in the email bounce log. Test mode rewrites recipients to a sink domain, which is the
  cheap way to generate events without hurting a real address.
- **The server error log** after exercising any of the above. A deprecation notice a user would
  see is a defect.
- **A live board that runs Hampel/Monolog with log-by-email enabled.** That combination is what
  5.0.0 broke, and no local install has it: this is the configuration with the least real-world
  evidence behind it, exactly as an install without Monolog was before 4.0.1 was published.
- **That an install below the PHP floor is refused cleanly.** 5.0.0 raised `require.php` from
  8.1.0 to 8.3.0, so some existing users will be blocked rather than upgraded. XenForo enforces
  that from `addon.json` before any of this add-on's code runs, which is why it is low risk — but
  it has not been exercised, and the sandbox can do it: a second instance with `PHP_VERSION=8.2`
  in `.env`, 4.0.1 installed, then an upgrade attempt that should refuse.

Checks about *server-rendered HTML* — is the nav entry present, did a phrase resolve or is a raw
key showing — are only here because dispatching a route in a test is not yet possible. They are
not really visual, and should move to `Automated` when that changes.
