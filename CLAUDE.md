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
php cmd.php xf-addon:build-release Hampel/SparkPostMail  # runs build.json, writes _releases/
```

Add `-v` / `-vv` / `-vvv` to the `sparkpost:*` commands — `Traits/OutputTrait` maps PSR-3 levels onto
Symfony verbosity, so `info` output only appears at `-v` and `debug` at `-vvv`.

**Tests do not currently run.** `tests/` is stale — it was written against the 3.x
`sparkpost/sparkpost` implementation and references classes deleted in the 4.0 rewrite
(`EmailBounce\EmailBounceProcessor`, `SparkPost\SparkPost`, `logJobProgress`, the `:MessageEvent` job
short name). `hampel/testing` is not in `composer.json` and there is no `vendor/bin/phpunit`, so
`phpunit.xml` has nothing to run. `README.md` records this as a TODO. Do not treat a green/absent test
run as verification; check behaviour through the CLI commands and the admin test page instead.

## Architecture

### Two things bolted onto XenForo's mail stack

**1. The transport.** `Listener::mailerTransportSetup` answers the `mailer_transport_setup` code event
and swaps in `SparkPostApiTransport` (from `hampel/symfonymailer-sparkpost`) whenever
`Option\EmailTransport::isSparkPostEnabled()`. Every extension in `XF/` guards on that same static —
the add-on must be inert when another transport is selected.

**2. The message-event pipeline**, which is how bounces and unsubscribes get back into XenForo.
SparkPost has no inbound bounce mailbox here; events are pulled from the API:

```
Cron/CLI -> FetchMessageEventsJob -> SparkPostApi (GET events/message, paged)
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
- three template modifications inject the SparkPost radio option and its sub-form;
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

Bounce handling is a `bounce_class` -> hard/soft/block/admin switch in `processBounce()`; the
`$classifications` table at the top of the service exists to phrase those classes for the admin UI.

### Logging is a soft dependency on Hampel/Monolog

`Listener::appSetup` binds `sparkpostmail.log` **only if** the container has `monolog` — the
Hampel/Monolog add-on is not in `addon.json` `require`, so `sparkpostmail.log` can legitimately be
`null`. Everything logs through `Traits/LogTrait` (or `LogConsoleTrait` in CLI), whose `log()` no-ops
when no logger was set. Never assume the logger exists.

`Traits/ContextAwareTrait` merges per-class context (`job`, `command`, `service`) into every record;
`AbstractLoggingJob` and `AbstractLoggingCommand` wire logger + context + API subcontainer in their
constructor/`initialize()`, so new jobs and commands should extend those rather than XF's base classes.

### `$this->api` is the SubContainer, not the API client

`ApiAwareTrait::$api` holds `SubContainer\SparkPost` (the `sparkpostmail.api` container key). The raw
`Api\SparkPostApi` is behind `->api()` on that. Jobs call the subcontainer's `getMessageEvents()` /
`getUri()` because those apply the batch-size option and the watermark; the raw client does not.

### Untrusted HTTP by default

`SparkPostApi::get()` uses `$app->http()->reader()->getUntrusted()` so calls go through a configured
proxy. Setting `$config['sparkPostApi']` in `src/config.php` overrides the base URL **and** switches to
trusted mode for a local API stub — dev only, and the only reason `SparkPostApi::__construct` takes
arguments.

## Add-on-specific traps

- **Composer under-declares production dependencies on purpose.** `Api/SparkPostApi.php` uses
  `GuzzleHttp\Utils`, the traits use `Psr\Log`, and the CLI commands use `Symfony\Component\Console` —
  all of which are `require-dev` here or absent. They resolve at runtime from XenForo's own
  `src/vendor`. Do not "fix" this by promoting them to `require`; that would ship a second copy of
  Guzzle inside the add-on's `vendor/` and conflict with XF's.
- **`hampel/symfonymailer-sparkpost` is abandoned upstream**, superseded by
  `hampel/sparkpost-transport`. This is a deliberate deferral, not an oversight: the replacement
  requires PHP 8.3 (this add-on's floor is 8.1), uses a different namespace, and is built on
  `hampel/sparkpost` — a client library that overlaps `Api/SparkPostApi.php`, so adopting it is a
  major release that probably retires that class. Stay on the 1.1.x line until that is scoped;
  `composer outdated` will keep reporting the abandonment in the meantime.
- **Incompatible with `Hampel/WndSparkPost`.** `Setup::checkRequirements` hard-fails if that adapter is
  installed — its What's New Digest handling moved in-house at 3.1.0 (`WhatsNewDigest/Job/SendDigest`).
- **`Hampel/SparkPost` is the Swiftmailer-era predecessor**, still on disk in `src/addons/`. It is a
  different add-on and read-only to this session; do not copy patterns from it, they are XF 2.2-era.
- **Test mode rewrites recipients.** `XF/Mail/Mail::setTo` appends `.sink.sparkpostmail.com` when test
  mode is on. If mail seems to vanish, check the transport option before anything else.
- **`Test/` and `tests/` are different things.** `Test/` is production code — the admin
  Tools > Test SparkPost Mail page, instantiated through the `sparkpostmail.test` container factory
  registered in `appAdminSetup`. `tests/` is the (stale) PHPUnit suite.
- **Root `*.md` does not ship.** `build.json` moves them into `_build/` and deletes `tests/`,
  `phpunit.xml` and `TESTING.md` from the release, so this file is safe where it is.
