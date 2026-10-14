<?php namespace Tests\Unit;

use Hampel\SparkPostMail\Listener;
use Tests\TestCase;

/**
 * The declaration answered to Hampel/AdminApi goes stale silently when an option is renamed or
 * added, so it is checked against `_output/options/` here. Neither test needs the Admin API
 * installed, and none of them needs a real credential.
 */
class SecretsDeclarationTest extends TestCase
{
	/**
	 * Declared secrets that are NOT members of an option this add-on owns, with the reason.
	 *
	 * There is no sparkpostmailApiKey: the key lives as an extra member inside XenForo's own
	 * emailTransport option, so there is no file under _output/options/ to check it against.
	 */
	private const FOREIGN_OPTIONS = [
		'emailTransport.apiKey' => 'a member this add-on adds to core\'s emailTransport option',
	];

	/** Options or members reading like a credential that are not one, each with its reason. */
	private const NOT_SECRETS = [];

	private const CREDENTIAL = '/key|token|secret|password|webhook/i';

	public function test_it_declares_this_add_on_only()
	{
		$this->assertSame(['Hampel/SparkPostMail'], array_keys($this->declarations()));
	}

	public function test_every_declared_secret_is_an_option_this_add_on_has_or_a_named_exception()
	{
		$problems = [];

		foreach ($this->declared()['options'] AS $path)
		{
			if (isset(self::FOREIGN_OPTIONS[$path]))
			{
				continue;
			}

			$parts = explode('.', $path);
			$file = $this->addOnRoot() . "/_output/options/{$parts[0]}.json";

			if (!is_file($file))
			{
				$problems[] = "{$path}: no such option, and not in FOREIGN_OPTIONS";
				continue;
			}

			$members = json_decode(file_get_contents($file), true)['sub_options'] ?? [];

			if (isset($parts[1]) && !in_array($parts[1], $members, true)
				&& !in_array('*', $members, true))
			{
				$problems[] = "{$path}: not a member of {$parts[0]}";
			}
		}

		$this->assertSame([], $problems);
	}

	/**
	 * The credential sweep below walks this add-on's own options, so it is structurally blind to
	 * a secret living in somebody else's - which is the only kind this add-on has. Measured:
	 * with this test absent, removing `emailTransport.apiKey` from the declaration failed
	 * nothing at all. Every foreign secret named above must therefore be asserted directly.
	 */
	public function test_every_foreign_secret_is_still_declared()
	{
		$this->assertSame(
			[],
			array_values(array_diff(array_keys(self::FOREIGN_OPTIONS), $this->declared()['options'])),
			'a secret named in FOREIGN_OPTIONS is no longer declared'
		);
	}

	public function test_an_option_or_member_that_reads_like_a_credential_is_declared_or_decided()
	{
		$decided = array_merge(self::NOT_SECRETS, $this->declared()['options']);

		$undecided = [];

		foreach ($this->optionIds() AS $optionId)
		{
			if (!in_array($optionId, $decided, true) && preg_match(self::CREDENTIAL, $optionId))
			{
				$undecided[] = $optionId;
			}

			$file = $this->addOnRoot() . "/_output/options/{$optionId}.json";

			foreach (json_decode(file_get_contents($file), true)['sub_options'] ?? [] AS $member)
			{
				$path = "{$optionId}.{$member}";

				if (preg_match(self::CREDENTIAL, $member) && !in_array($path, $decided, true))
				{
					$undecided[] = $path;
				}
			}
		}

		$this->assertSame([], $undecided, 'declare each as a secret, or add it to NOT_SECRETS');
	}

	/**
	 * A claimed config.php key has to be one the add-on actually reads, or it claims nothing and
	 * the real key stays unreportable. Checked against the source rather than by setting a value,
	 * because this add-on reads the key through $app->config() at a container seam rather than
	 * through a static getter of its own.
	 */
	public function test_every_claimed_config_key_is_one_the_add_on_reads()
	{
		$source = '';

		foreach (glob($this->addOnRoot() . '/{*.php,*/*.php,*/*/*.php}', GLOB_BRACE) ?: [] AS $file)
		{
			$source .= file_get_contents($file);
		}

		$unread = [];

		foreach (array_keys($this->declared()['config']) AS $key)
		{
			if (!str_contains($source, "config('{$key}')"))
			{
				$unread[] = $key;
			}
		}

		$this->assertSame([], $unread, 'a claimed config.php key this add-on never reads');
	}

	private function declarations(): array
	{
		$declarations = [];
		Listener::adminApiRedaction($declarations);

		return $declarations;
	}

	private function declared(): array
	{
		return $this->declarations()['Hampel/SparkPostMail'] + ['options' => [], 'config' => []];
	}

	private function optionIds(): array
	{
		$ids = [];

		foreach (glob($this->addOnRoot() . '/_output/options/*.json') ?: [] AS $file)
		{
			if (basename($file) != '_metadata.json')
			{
				$ids[] = basename($file, '.json');
			}
		}

		return $ids;
	}

	private function addOnRoot(): string
	{
		return dirname(__DIR__, 2);
	}
}
