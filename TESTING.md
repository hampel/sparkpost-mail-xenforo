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
- **Upgrade from a built release**, not from the working copy — install the previous version on a
  second XenForo install and upgrade it from the zip, as a user would. A `Setup.php` step that
  works on a fresh install and fails on upgrade is the classic failure here, and no amount of
  testing the working copy finds it.
- **The server error log** after exercising any of the above. A deprecation notice a user would
  see is a defect.
- **Upgrading from a built zip needs a second install that satisfies `require.XF`, and it must
  not be a development checkout.** The add-on requires XenForo 2.3.0+, so an install below that
  floor is not a target at all.

  **A development install is not a substitute either**, and this is the trap worth stating: there
  the add-on directory *is* the working copy, so installing a release zip over it does not
  simulate a user upgrade - it replaces the checkout with release files, dropping `tests/`,
  `build.json` and `_output/` and stripping dev dependencies from `vendor/`. An upgrade can only
  be exercised where the add-on was installed from a zip to begin with.

  Where no such install exists, say so rather than reporting the path as untested-but-fine, and
  fall back to reading `Setup.php` for `upgrade*()` steps gated between the last **published**
  version and the one being released. If none are gated in that range, the classic
  install-works/upgrade-fails failure cannot occur and the risk is genuinely low.

Checks about *server-rendered HTML* — is the nav entry present, did a phrase resolve or is a raw
key showing — are only here because dispatching a route in a test is not yet possible. They are
not really visual, and should move to `Automated` when that changes.
