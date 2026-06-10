<?php

namespace App\Tests\Controller;

use App\Tests\Support\UserFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Proves the controller actually enforces the per-IP rate limiter. The test env
 * configures the limiters as `no_limit` (so the other functional tests aren't
 * throttled on the shared 127.0.0.1 client IP), so this test swaps in a real
 * sliding-window limiter with limit 1 and asserts a rate-limited request is
 * dropped before any email is sent — while still returning the generic redirect
 * so rate-limiting isn't itself an enumeration oracle.
 */
class ResetPasswordRateLimitTest extends WebTestCase
{
    use UserFactoryTrait;

    public function test_ip_rate_limited_request_sends_no_email_but_stays_generic(): void
    {
        $client = static::createClient();
        // disableReboot keeps our overridden service alive across the GET + POST;
        // a reboot would rebuild the container and restore the no_limit limiter.
        $client->disableReboot();
        $client->enableProfiler();

        $factory = new RateLimiterFactory(
            ['id' => 'password_reset_request', 'policy' => 'sliding_window', 'limit' => 1, 'interval' => '15 minutes'],
            new InMemoryStorage(),
        );
        // Pre-exhaust this IP's single token so the controller's own consume()
        // is the one that gets rejected.
        $factory->create('127.0.0.1')->consume(1);
        $client->getContainer()->set('limiter.password_reset_request', $factory);

        $user = $this->persistUser($client, 'ratelimit-user', email: 'ratelimit@example.test');

        $crawler = $client->request('GET', '/netBS/secure/forgot-password');
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[username]'] = $user->getUsername();
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertEmailCount(0);
    }
}
