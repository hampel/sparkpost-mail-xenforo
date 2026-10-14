# SparkPost Symfony Mailer Transport for XenForo 2.3

This XenForo 2.3 addon provides a Symfony Mailer Transport implementation for SparkPost.

Requires a [SparkPost](https://sparkpost.com/) account.

Minimum of PHP 8.3 and XenForo 2.3 required.

More information here: [SparkPost Mail Transport for XF 2.3](https://xenforo.com/community/resources/sparkpost-mail-transport-for-xf-2-3.9538/)

By [Simon Hampel](https://xenforo.com/community/members/sim.4264/).

## Self-checks

```bash
php cmd.php sparkpost:config      # what this forum is set up to do, and where each value came from
php cmd.php sparkpost:validate    # prove it can actually do it
```

`sparkpost:config` prints every setting with its source — the options page or `config.php` — and
never prints the API key, only that it is set and which host it points at.

`sparkpost:validate` checks the wiring, the staging table, the cron entries and the fetch
watermark, then makes one authenticated read from SparkPost to confirm the key is accepted. It
sends no mail. `--unattended` skips that call, for a gate nobody is watching.

| | all fine | warnings only | any failure |
|---|---|---|---|
| `sparkpost:validate` | 0 | 0 | 1 |
| `sparkpost:validate --strict` | 0 | 2 | 1 |

A warning exits 0 without `--strict`, so `sparkpost:validate && next-step` gates on failure
alone. **This is not the monitoring-plugin numbering**, where 1 means warning and 2 means
critical: here 1 always means something failed, with the flag and without it.
