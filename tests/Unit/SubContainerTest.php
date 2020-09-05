<?php namespace Tests\Unit;

use Carbon\Carbon;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hampel\SparkPostMail\EmailBounce\Processor;
use Hampel\SparkPostMail\Listener;
use Hampel\SparkPostMail\SubContainer\SparkPost;
use Mockery as m;
use Psr\Http\Message\ResponseInterface;
use SparkPost\SparkPostPromise;
use Tests\TestCase;
use XF\Container;

class SubContainerTest extends TestCase
{
	/** @var SparkPost */
	protected $sp;

	public function setUp(): void
	{
		parent::setUp();

		$this->sp = new SparkPost($this->app()->container(), $this->app());
	}

	// -------------------------------------------------------

	public function test_initialisation()
	{
		$this->setOptions([
			'emailTransport' => [
				'emailTransport' => 'sparkpost',
				'apiKey' => 'foo'
			],
		]);

		$this->assertInstanceOf(\SparkPost\SparkPost::class, $this->sp->api());
		$this->assertInstanceOf(Processor::class, $this->sp->bounce());
		$this->assertIsArray($this->sp->getBounceMessageEventTypes());
	}

	public function test_sampleMessageEvents()
	{
		$this->mock([$this->sp, 'api'], \SparkPost\SparkPost::class, function ($mock) {
			$promise = m::mock(SparkPostPromise::class, function ($mock) {
				$response = m::mock(ResponseInterface::class, function ($mock) {
					$samples = json_decode($this->getMockData('message-event-samples.json'), true);

					$mock->expects()->getBody()->andReturns($samples);
				});

				$mock->expects()->wait()->andReturns($response);
			});

			$mock->expects()->request('GET', 'events/message/samples', ['events' => ['foo', 'bar']])->andReturns($promise);
		});

		$response = $this->sp->sampleMessageEvents(['foo', 'bar']);
		$this->assertIsArray($response);
		$this->assertArrayHasKey('results', $response);
	}

	public function test_getMessageEvents_default()
	{
		$this->mock([$this->sp, 'api'], \SparkPost\SparkPost::class, function ($mock) {
			$promise = m::mock(SparkPostPromise::class, function ($mock) {
				$response = m::mock(ResponseInterface::class, function ($mock) {
					$events = json_decode($this->getMockData('message-events.json'), true);

					$mock->expects()->getBody()->andReturns($events);
				});

				$mock->expects()->wait()->andReturns($response);
			});

			$mock->expects()
			     ->request('GET', 'events/message', [
			        'page' => 1,
			        'per_page' => 10,
			     ])
			     ->andReturns($promise);
		});

		$response = $this->sp->getMessageEvents();

		$this->assertIsArray($response);
		$this->assertArrayHasKey('results', $response);
		$this->assertArrayHasKey('total_count', $response);
		$this->assertArrayHasKey('links', $response);
	}

	public function test_getMessageEvents_options()
	{
		$from = Carbon::now()->subDays(1)->timestamp;
		$fromSp = urlencode(Carbon::createFromTimestamp($from)->format("Y-m-d\TH:i"));
		$to = Carbon::now()->timestamp;
		$toSp = urlencode(Carbon::createFromTimestamp($to)->format("Y-m-d\TH:i"));

		$this->mock([$this->sp, 'api'], \SparkPost\SparkPost::class, function ($mock) use ($fromSp, $toSp) {
			$promise = m::mock(SparkPostPromise::class, function ($mock) {
				$response = m::mock(ResponseInterface::class, function ($mock) {
					$events = json_decode($this->getMockData('message-events.json'), true);

					$mock->expects()->getBody()->andReturns($events);
				});

				$mock->expects()->wait()->andReturns($response);
			});

			$mock->expects()
			     ->request('GET', 'events/message', [
			        'events' => ['foo', 'bar'],
			        'page' => 2,
			        'per_page' => 5,
			        'from' => $fromSp,
			        'to' => $toSp,
			     ])
			     ->andReturns($promise);
		});

		$response = $this->sp->getMessageEvents(2, 5, ['foo', 'bar'], $from, $to);

		$this->assertIsArray($response);
		$this->assertArrayHasKey('results', $response);
		$this->assertArrayHasKey('total_count', $response);
		$this->assertArrayHasKey('links', $response);
	}

	public function test_getUri()
	{
		$this->mock([$this->sp, 'api'], \SparkPost\SparkPost::class, function ($mock) {
			$promise = m::mock(SparkPostPromise::class, function ($mock) {
				$response = m::mock(ResponseInterface::class, function ($mock) {
					$events = json_decode($this->getMockData('message-events.json'), true);

					$mock->expects()->getBody()->andReturns($events);
				});

				$mock->expects()->wait()->andReturns($response);
			});

			$mock->expects()->request('GET', '/foo')->andReturns($promise);
		});

		$response = $this->sp->getUri('/foo');
		$this->assertIsArray($response);
		$this->assertArrayHasKey('results', $response);
	}

	public function test_timestampToSparkPostDate()
	{
		$date = mktime(12, 0, 0, 1, 1, 2000);

		$this->assertEquals('2000-01-01T12%3A00', $this->sp->timestampToSparkPostDate($date));
	}

	public function test_mail_transport()
	{
		$this->swapMailerTransport(false, false);

		$this->fakesHttp([
			new Response(200, [], $this->getMockData('mail.json'))
		]);

		$mail = $this->app->mailer()->newMail();
		$mail->setTo('foo@example.com');
		$mail->setContent('Foo', '<p>bar</p>');
		$mail->send();

		$history = $this->getHttpHistory();

		$this->assertIsArray($history);
		$this->assertArrayHasKey(0, $history);
		$this->assertArrayHasKey('request', $history[0]);

		/** @var Request $request */
		$request = $history[0]['request'];

		$this->assertEquals(Request::class, get_class($request));

		$body = json_decode($request->getBody()->getContents(), true);

		$this->assertIsArray($body);
		$this->assertTrue(isset($body['recipients'][0]['address']['email']));
		$this->assertEquals('foo@example.com', $body['recipients'][0]['address']['email']);

		$this->assertTrue(isset($body['content']['subject']));
		$this->assertEquals('Foo', $body['content']['subject']);

		$this->assertTrue(isset($body['options']['transactional']));
		$this->assertTrue($body['options']['transactional']);
		$this->assertTrue(isset($body['options']['open_tracking']));
		$this->assertFalse($body['options']['open_tracking']);
		$this->assertTrue(isset($body['options']['click_tracking']));
		$this->assertFalse($body['options']['click_tracking']);
	}

	public function test_mail_transport_tracking()
	{
		$this->swapMailerTransport(true, true);

		$this->fakesHttp([
			new Response(200, [], $this->getMockData('mail.json'))
		]);

		$mail = $this->app->mailer()->newMail();
		$mail->setTo('foo@example.com');
		$mail->setContent('Foo', '<p>bar</p>');
		$mail->send();

		$history = $this->getHttpHistory();

		$this->assertIsArray($history);
		$this->assertArrayHasKey(0, $history);
		$this->assertArrayHasKey('request', $history[0]);

		/** @var Request $request */
		$request = $history[0]['request'];

		$this->assertEquals(Request::class, get_class($request));

		$body = json_decode($request->getBody()->getContents(), true);

		$this->assertIsArray($body);
		$this->assertTrue(isset($body['options']['open_tracking']));
		$this->assertTrue($body['options']['open_tracking']);
		$this->assertTrue(isset($body['options']['click_tracking']));
		$this->assertTrue($body['options']['click_tracking']);
	}

	public function test_setNonTransactional()
	{
		$this->setOptions([
			'emailTransport' => [
				'emailTransport' => 'sparkpost',
				'apiKey' => 'foo'
			],
		]);

		$this->swapMailerTransport();

		$this->fakesHttp([
			new Response(200, [], $this->getMockData('mail.json'))
		]);

		$mail = $this->app->mailer()->newMail();
		$mail->setTo('foo@example.com');
		$mail->setContent('Foo', '<p>bar</p>');
		$this->sp->setNonTransactional($mail)->send();

		$history = $this->getHttpHistory();

		$this->assertIsArray($history);
		$this->assertArrayHasKey(0, $history);
		$this->assertArrayHasKey('request', $history[0]);

		/** @var Request $request */
		$request = $history[0]['request'];

		$this->assertEquals(Request::class, get_class($request));

		$body = json_decode($request->getBody()->getContents(), true);

		$this->assertTrue(isset($body['options']['transactional']));
		$this->assertFalse($body['options']['transactional']);
	}

	// ----------------------------------------

	protected function swapMailerTransport($openTracking = false, $clickTracking = false)
	{
		$this->swap('mailer.transport', function (Container $c) use ($openTracking, $clickTracking) {
			return Listener::getMailerTransport('foo', $openTracking, $clickTracking);
		});
	}
}
