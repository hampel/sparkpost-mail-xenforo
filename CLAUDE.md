# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Read the install root's `AGENTS.md` first for the install-wide conventions (the `_output`/`_data`
boundary, `cmd.php` signatures, per-add-on git and Composer layout). This file covers only what is
specific to **Hampel/SparkPostMail**.

## Commands

Run these from the add-on directory; `cmd.php` resolves the install from its own path, not from
the working directory, so `php <install>/cmd.php` works unchanged from here.

```bash
php cmd.php sparkpost:fetch-message-events      # queue + run FetchMessageEventsJob
php cmd.php sparkpost:process-message-events    # queue + run ProcessMessageEventsJob
php cmd.php sparkpost:prune-message-events -d 7 # delete processed events older than N days (default 28)
php cmd.php sparkpost:reset-message-events      # clear the simpleCache watermark (see below)

php cmd.php xf-dev:import --addon=Hampel/SparkPostMail   # _output/ -> database, after editing _output
php cmd.php xf-addon:export Hampel/SparkPostMail         # database -> _output/
php cmd.php xf-addon:build-release Hampel/SparkPostMail  # runs build.json, writes _releases/ (see below)
```

**A build overwrites `_releases/<addon>-<version>.zip` with no warning.** The filename comes from
`addon.json`'s version string and `ReleaseBuilderService::finalizeRelease()` renames over the
destination without checking, so a test build on a branch still carrying the last released version
string destroys that release's artifact. Bump to an alpha at the start of a development cycle — see
the `xenforo-addon-release` skill — or move `_releases/` aside before building.

Add `-v` / `-vv` / `-vvv` to the `sparkpost:*` commands — `Traits/OutputTrait` maps PSR-3 levels onto
Symfony verbosity, so `info` output only appears at `-v` and `debug` at `-vvv`.

```bash
composer install          # first time; vendor/ is gitignored
vendor/bin/phpunit        # whole suite
vendor/bin/phpunit --filter MessageEventWindowTest
```

**The 3.x suite is gone.** It was written against the `sparkpost/sparkpost` implementation and
every one of its tests referenced classes the 4.0 rewrite deleted, so it was removed rather than
repaired. What replaced it covers the seams with a history of defects — the API date/URI helpers
(the 2.1.1 paging-prefix fix), the fetch window (the 2.1.4 minimum-width fix), the cron
enable-guards, the Monolog-absent logger fallback, bounce classification and fetch paging.

`MessageEventService` is covered end to end — message-type dispatch, bounce classification, the
campaign-prefix unsubscribe routing and both of its fallbacks, and the batch loop — and
`ProcessMessageEventsJob`'s sizing is covered around it.

**Test the add-on's side of a core call, not the core call.** `calculateOptimalBatch()` is
`XF\Job\AbstractJob`'s, so `ProcessBatchSizingTest` asserts the arguments and what is done with
the answer — the default batch of 100, the 1000 ceiling passed at the call site, the counters,
completing rather than resuming on an empty batch — and never the formula. Pinning core's
arithmetic would break this suite on an XF upgrade for something that is not the add-on's
behaviour.

The suite runs on PHPUnit 10, 11 and 12. **Use `#[DataProvider]`, never the `@dataProvider`
annotation** — the add-on declares no PHPUnit constraint of its own, so the test framework's range
chooses one, and PHPUnit 12 removed metadata in doc comments. `tests/Feature/.gitkeep` is not
decoration: `phpunit.xml` declares a Feature suite and git does not track empty directories, so
without it a clean clone aborts with `Test directory not found` (exit 2) and runs nothing at all.

**Read a PHPUnit exit code unpiped.** `phpunit | tail; echo $?` reports *tail's* status, which is
0 whatever PHPUnit did — that mismeasurement turned an abort into an apparent silent pass in this
add-on's own trial report. `phpunit >/dev/null; echo $?` is the check.

Four things that cost time here, worth knowing before adding tests:

- **`mockRepository()` needs the XF short name, not the class name.** `getRepository()` normalises
  its argument (`classToString`, then strip the trailing `Repository`), so `MessageEventRepository::class`
  is stored under a key the lookup never asks for and the mock silently does not apply. Use
  `'Hampel\SparkPostMail:MessageEvent'`, or avoid the problem — `MessageEventWindowTest` seeds
  `fakesSimpleCache()` instead, which exercises the real repository as a bonus.
- **`mockDatabase()` rebuilds the entity manager and discards repository mocks.** Anything
  registered by `mockRepository()` before it is silently dropped and the real repository runs, so
  mock the database first. Its `fetchAll` must also return an array rather than null, because
  rebuilding the EM re-runs the `$addonsToLoad` listener query through the mock.
- **`mockService()` replaces the container's whole `service` factory**, so every later
  `app()->service()` call returns a mock — resolve the service under test before calling it. Give
  it the full class name after the colon (`Hampel\SparkPostMail:MessageEventService`): the short
  form resolves to `Service\MessageEvent`, which does not exist, and Mockery mocks a missing class
  without complaining, so the double is simply untyped.
- **`Entity::save()` is `final`**, so an entity double cannot intercept it. Tests that need a save
  to happen use real entities with the database mocked out, and set the primary key with
  `setTrusted()` — assigning it normally sends the entity to the finder for a uniqueness check.
- **Mutation-check anything you add.** Every assertion here was confirmed to fail when the
  behaviour it names is removed.

## Architecture

### Two things bolted onto XenForo's mail stack

**1. The transport.** `Listener::mailerTransportSetup` answers the `mailer_transport_setup` code event
and swaps in `SparkPostTransport` (from `hampel/sparkpost-transport`) whenever
`Option\EmailTransport::isSparkPostEnabled()`. Every extension in `XF/` guards on that same static —
the add-on must be inert when another transport is selected.

**2. The message-event pipeline**, which is how bounces and unsubscribes get back into XenForo.
SparkPost has no inbound bounce mailbox here; events are pulled from the API:

```
Cron/CLI -> FetchMessageEventsJob -> hampel/sparkpost (GET events/message, cursor-paged)
                                  -> MessageEventRepository::storeMessageEvent
                                  -> xf_sparkpost_mail_message_event (processed = 0)

Cron/CLI -> ProcessMessageEventsJob -> MessageEventService::processEvents
                                    -> XF\EmailBounce\Processor::takeBounceAction  (bounces)
                                    -> XF\Service\User\EmailStopService            (unsubscribes)
                                    -> xf_email_bounce_log
                                    -> marks processed = 1

Cron (monthly) / CLI -> MessageEventRepository::pruneMessageEvents
```

Fetch and process are deliberately separate jobs over a staging table: the API is rate-limited and
paged, the processing is per-user and slow, and either half can be re-run without the other.

Three cron entries drive it (`_output/cron_entries/`): fetch at :21/:51, process at :26/:56, prune
daily at 18:12 (`-1` in XF's `run_rules` means "any", not "last"). The CLI commands enqueue **the same job classes under the same unique keys**
(`SparkPostMailFetchMessageEvents`, `SparkPostMailProcessMessageEvents`) via `JobRunnerTrait`, so a
manual run and a cron run cannot collide — but a manual run will also pick up whatever cron left
in-flight.

### The `last_run` watermark is in the simpleCache, not the database

`SubContainer\SparkPost::getMessageEvents()` computes the API `from` time from
`MessageEventRepository::getLastRun()`, which reads XF's simpleCache set `Hampel/SparkPostMail`, key
`message-event`. `FetchMessageEventsJob::complete()` writes it back. Consequences worth knowing before
debugging "no events found":

- With no cached value the first run reaches back **11 days** and pulls everything.
- The watermark is `query_start` captured at the *start* of the job, not the end.
- Clearing it is `sparkpost:reset-message-events`; a cache flush does the same thing accidentally.
- There is a floor of 60 seconds between `from` and `to` — SparkPost rejects a zero-width window.

### Options are stored inside XenForo's own `emailTransport` option

There is no `sparkpostmailApiKey` option row. The API key, click/open tracking and test mode all live
as extra keys inside the core `emailTransport` option value. That is why:

- `XF/Admin/Controller/Option.php` intercepts `actionEmailTransportSetup` for `new_type == 'sparkpost'`
  and adds `actionEmailTransportSparkpost` to write those keys;
- two template modifications inject the SparkPost radio option and its sub-form —
  `option_email_transport_setup` and `option_template_advancedEmailTransport`;
- **all reads go through the static helpers in `Option/EmailTransport.php`** — never touch
  `\XF::options()->emailTransport` directly, the shape is only guaranteed there.
- `Setup::upgrade()` migrated the pre-2.0 standalone options into this structure; leave it alone.

`sparkpostmailMessageEventsBatchSize` is the one real option (API page size, default 1000).

### Transactional vs non-transactional is the whole unsubscribe design

`XF/Mail/Mailer::applyMailDefaults` marks **every** email transactional, then individual extensions
opt specific mail out: `XF/Job/UserEmail`, `XF/Service/User/Welcome`,
`XF/Admin/Controller/Tools::getMail`, `WhatsNewDigest/Job/SendDigest`. `XF/Mail/Mail::setTemplate`
sets the SparkPost `campaign_id` to the **template name**, and `setToUser` attaches `username`/`user_id`
as metadata.

`MessageEventService::processUnsubscribe` then reads that campaign back and prefix-matches it against a
stop map to decide *what* to unsubscribe the user from. A new mail type therefore needs its template
name adding to the right map, or the user gets unsubscribed from everything as the fallback. The
non-transactional map is extensible from other add-ons via the
`sparkpostmail_non_transactional_stop_map` code event, and `stopAllNonTransactional()` is designed to be
overridden.

Bounce handling maps the package's `BounceClass` to a `BounceClassification` and matches over that
in `processBounce()`. `Informational` (auto-reply, subscribe) means the message was delivered and
must take no action against the user; admin failures are treated as hard bounces.

**The match's `default` arm is load-bearing, not defensive padding.** The package adds enum cases
in a **minor** version, so a classification this add-on has never seen arrives through an ordinary
`composer update` — and while the match was exhaustive that was an `UnhandledMatchError` in the
middle of `ProcessMessageEventsJob`, which is exactly what `Informational` was in 0.4.0. The arm
routes to `unmappedClassification()`, which takes no action and logs a warning: an unrecognised
classification is not evidence that an address is bad, and stopping someone's mail on a guess is
the one outcome that cannot be undone from here.

**So the loud signal moved to CI, and that is where it belongs.**
`test_every_bounce_classification_is_explicitly_mapped` lists the classifications this add-on has
decided about and fails on the bump that adds one, naming the decision.
`test_every_bounce_class_the_package_knows_can_be_processed` still walks every code, but with the
default arm in place it no longer detects a new classification by itself.

### The mail transport must never resolve the logger while it is being built

`SubContainer\SparkPost` binds `log` to a `Log\LazyLogger` and passes **that** to
`SparkPostClient` and `SparkPostTransport`. Do not "simplify" it back to
`$this->parent['sparkpostmail.log']`.

The two are on the same cycle. Hampel/Monolog's email handler calls `XF\App::mailer()` while it
builds a channel; the mailer builds its transport by firing `mailer_transport_setup`; this add-on
answers that event by building the transport. `XF\Container::offsetGet()` caches only *after* the
closure returns, so the second pass rebuilds instead of reusing and the recursion runs until the
stack is exhausted — `Maximum call stack size ... Infinite recursion?` at `XF/Container.php:29`.

**5.0.0 shipped this and production hit it within the hour.** 4.0.1 could not: its transport took
an API key and an HTTP client, and only the `api` entry — used by the jobs — called `setLogger()`.
The package's transport and client both take a `LoggerInterface`, which is what moved the logger
onto the branch the mailer builds.

**Both halves are absent on a development install**, which is why the whole test suite, an audit
and a sandbox upgrade all missed it: `monologSendEmail` defaults to disabled, so no mail handler
is pushed, and a dev board rarely selects SparkPost. `TransportLoggerCycleTest` reproduces it
without Monolog by binding `sparkpostmail.log` to a closure that builds the mailer, and counts
re-entry rather than letting the stack overflow take the PHPUnit process with it.

`Log\LazyLogger` carries **no type declarations**, deliberately. XF's autoloader registers first
and ships psr/log 1.1.4, whose `LoggerInterface::log()` is untyped with no return type — that is
the contract regardless of what this add-on's own lock resolves.

### Logging is a soft dependency on Hampel/Monolog

`Listener::appSetup` binds `sparkpostmail.log` **only if** the container has `monolog` — the
Hampel/Monolog add-on is not in `addon.json` `require`, so `sparkpostmail.log` can legitimately be
`null`. Everything logs through `Traits/LogTrait` (or `LogConsoleTrait` in CLI), whose `log()` no-ops
when no logger was set. Never assume the logger exists.

`Traits/ContextAwareTrait` merges per-class context (`job`, `command`, `service`) into every record;
`AbstractLoggingJob` and `AbstractLoggingCommand` wire logger + context + API subcontainer in their
constructor/`initialize()`, so new jobs and commands should extend those rather than XF's base classes.

### What the transmission log line carries, and what it used to

`hampel/sparkpost` **1.1.1** logs a description of the send rather than the payload:
`campaign_id`, `template_id`, `recipient_count`, `recipients` (addresses, capped at ten),
`transactional`, `sandbox`, `attachment_count`, `inline_image_count`, `has_substitution_data` and
`return_path`. A second `debug` line, `SparkPost transmission sent`, carries `transmission_id`,
`total_accepted_recipients` and `total_rejected_recipients`. The subject, both bodies,
`content.headers` and `substitution_data` are not logged at any level. The payload-describing
helper is `describe()`; it was called `redact()` before 1.1.1, which is worth knowing only because
older notes refer to it.

**Recipient addresses are still logged, so a `debug` trial still writes personal data.** That is
deliberate and within the house policy — for a transport, which recipient a transmission went to
is the point of tracing a delivery complaint — but it is the thing to tell whoever raises the
level.

**A trial can be scoped to one channel, which it could not when this add-on first met the
problem.** `Hampel/Monolog` 5.x takes a per-channel level in `config.php`, and this add-on's
channel is `sparkpost`:

```php
$config['monolog']['file']['channels'] = ['knownbots' => 'debug'];
```

That raises only the named channel and leaves every other at the section's level, so a trial aimed
at another add-on no longer turns this one up. It is config-only, deliberately — Monolog's README
says it belongs in a file someone has to edit to undo rather than on the options page.

**Before 1.1.1 this line carried the whole transmission**, subject and HTML and text verbatim.
Measured on 2026-10-01: an hour at `debug` logged each outgoing email with a 12,222-character HTML
body, so every notification body, password-reset link and email-confirmation link went into the
log store. On the `Hampel/Monolog` of the time the minimum level was **global to the forum**, so it
could not be scoped away — which is how it happened, during a trial aimed at a different add-on.
Reported on 2026-10-03 and fixed on 2026-10-04. Monolog 5.x has since added the per-channel level
above, so that half of the trap is gone.

Two things survive the fix. **Records written before it are still in the store** and ageing out on
their own. And the lesson, which is the reusable half: **a mocked logger asserts nothing about
whether it should have been handed what it was handed**, so no suite here could have caught it —
it was found by reading what had been shipped.

The `error` path was the same shape at a level production actually writes, and is fixed in the same
release: `Connection.php` logged the whole response body, and now logs `status`, `method`, `uri`,
the parsed `errors[]`, `body_length` and `content_type`.

### `$this->api` is the SubContainer, not the API client

`ApiAwareTrait::$api` holds `SubContainer\SparkPost` (the `sparkpostmail.api` container key). The raw
The package client is behind `->sparkpost()` on that. The fetch job asks the sub-container for
`buildEventQuery()` rather than assembling the query itself, because that is where the batch-size
option and the watermark are applied.

### Untrusted HTTP by default

`Http\ReaderClient` is a PSR-18 client over `XF\Http\Reader`, so the package shares XenForo's own
outbound stack. It uses `requestUntrusted()` so calls go through a configured
proxy. Setting `$config['sparkPostApi']` in `src/config.php` overrides the base URL **and** switches to
trusted mode for a local API stub — dev only. The url goes to the package's `Config`, the trust
decision to the adapter.

## Add-on-specific traps

- **Composer under-declares production dependencies on purpose.** The add-on's own code uses
  `GuzzleHttp\Utils`, the traits use `Psr\Log`, and the CLI commands use `Symfony\Component\Console` —
  all of which are `require-dev` here or absent. They resolve at runtime from XenForo's own
  `src/vendor`. Do not "fix" this by promoting them to `require`; that would ship a second copy of
  Guzzle inside the add-on's `vendor/` and conflict with XF's.
- **On a dependency bump, read the package's `CHANGELOG.md` before working out what changed.**
  `hampel/sparkpost` and `hampel/sparkpost-transport` ship theirs inside the distribution, so once
  `composer update` has run they are sitting at `vendor/hampel/<package>/CHANGELOG.md`. Their
  breaking changes are the kind nothing static catches — 0.4.0 added a `BounceClassification` case,
  which turned the exhaustive `match` in `processBounce()` into an `UnhandledMatchError` at runtime,
  inside whatever job was processing events. Diffing the installed `src/` against the previous
  version, or relying on a summary from elsewhere, reconstructs a document you already have.

  **Being on 1.x does not rule that out, so read every minor's entry.** The package's own
  stability statement, added in 1.1.0, is that `BounceClass` and `EventType` **gain cases in a
  minor**, and that a `match` over either therefore needs a `default` arm. It does not say which
  way `BounceClassification` goes, which is the enum `processBounce()` matches over — so that
  match now has a `default` arm too, and a new classification is a red test rather than a fatal
  job. See *Transactional vs non-transactional* above for what the arm does and why it does
  nothing.
- **Under a tilde, the third digit changes the meaning; under a caret it does not.** Both packages
  are pinned `^1.0`, which is `>=1.0.0 <2.0.0` — identical to `^1.0.0`. `~1.0` is the same again,
  but `~1.0.0` is `>=1.0.0 <1.1.0`, which would pin the add-on to one minor line and put back the
  coordinated bump that 0.x forced. Verified against `Composer\Semver\Semver`, and got backwards
  once by each of the two package sessions, so check it rather than recalling it.
- **`tests/mock/*.json` are real SparkPost payloads and are load-bearing.** The paged pair
  (`message-events-initial` + `message-events-page2`) is what `FetchPagingTest` drives the job
  through; this board has no bounce events, so paging cannot be exercised against the live API.
- **Mock repositories by XF short name, not class name.** `getRepository()` normalises its
  argument, so `MessageEventRepository::class` registers a mock nothing looks up and the real
  repository runs — a zero-call count, not an error. Use `'Hampel\SparkPostMail:MessageEvent'`.
- **Incompatible with `Hampel/WndSparkPost`.** `Setup::checkRequirements` hard-fails if that adapter is
  installed — its What's New Digest handling moved in-house at 3.1.0 (`WhatsNewDigest/Job/SendDigest`).
- **`Hampel/SparkPost` is the Swiftmailer-era predecessor**, still on disk in `src/addons/`. It is a
  different add-on and read-only to this session; do not copy patterns from it, they are XF 2.2-era.
- **Test mode rewrites the SMTP envelope**, not the To: header — `SinkEnvelopeListener` is registered on
  the transport only when test mode is on, so a test message reads as addressed to the real recipient.
  If mail seems to vanish, check the transport option before anything else.
- **`Test/` and `tests/` are different things.** `Test/` is production code — the admin
  Tools > Test SparkPost Mail page, instantiated through the `sparkpostmail.test` container factory
  registered in `appAdminSetup`. `tests/` is the PHPUnit suite.
- **Root `*.md` does not ship.** `build.json` moves them into `_build/` and deletes `tests/`,
  `phpunit.xml` and `TESTING.md` from the release, so this file is safe where it is.
