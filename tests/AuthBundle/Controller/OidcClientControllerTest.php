<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use App\Tests\Support\UserFactoryTrait;
use NetBS\AuthBundle\Service\HydraAdminClient;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The OAuth client admin pages against an in-memory Hydra admin API.
 */
final class OidcClientControllerTest extends WebTestCase
{
    use UserFactoryTrait;

    private const URL = '/netBS/secure/administration/authentification/clients';
    private const SLUG = 'client-wiki';

    private KernelBrowser $client;

    /** @var list<MockResponse|\Closure(): MockResponse> responses Hydra gives, in order */
    private array $hydraResponses = [];

    /** @var list<array{method: string, url: string, body: mixed}> */
    private array $hydraRequests = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // One kernel for the whole test, so every request sees the open transaction.
        $this->client->disableReboot();
        $this->db = $this->client->getContainer()->get('doctrine.dbal.default_connection');
        $this->db->beginTransaction();

        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? '';
            $this->hydraRequests[] = [
                'method' => $method,
                'url' => (string) parse_url($url, PHP_URL_PATH),
                'body' => \is_string($body) && $body !== '' ? json_decode($body, true) : null,
            ];
            self::assertNotEmpty($this->hydraResponses, sprintf('unexpected Hydra call %s %s', $method, $url));

            $next = array_shift($this->hydraResponses);

            return $next instanceof \Closure ? $next() : $next;
        }, 'http://hydra.test');
        static::getContainer()->set(HydraAdminClient::class, new HydraAdminClient($http, new NullLogger()));
    }

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    /**
     * @dataProvider adminRequests
     */
    public function testNonAdminIsForbidden(string $method, string $path): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'oidc-page-user', null, null, ['ROLE_USER']), 'netbs');

        $this->client->request($method, self::URL . $path);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->hydraRequests);
    }

    public static function adminRequests(): iterable
    {
        yield 'index' => ['GET', ''];
        yield 'create' => ['POST', '/new'];
        yield 'edit' => ['GET', '/' . self::SLUG . '/edit'];
        yield 'regenerate secret' => ['POST', '/' . self::SLUG . '/regenerate-secret'];
        yield 'delete' => ['POST', '/' . self::SLUG . '/delete'];
    }

    public function testIndexListsHydraClients(): void
    {
        $this->loginAdmin();
        $this->hydraReturns($this->json([self::hydraClient()]));

        $crawler = $this->client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('a[href="%s/%s/edit"]', self::URL, self::SLUG)));
    }

    public function testIndexStillRendersWhenHydraIsDown(): void
    {
        $this->loginAdmin();
        $this->hydraReturns($this->unreachable());

        $crawler = $this->client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('a[href$="/edit"]'));
        self::assertCount(1, $crawler->filter(sprintf('form[action="%s/new"]', self::URL)), 'creating stays possible');
    }

    public function testCreateShowsTheSecretOnceThenNeverAgain(): void
    {
        $this->loginAdmin();
        $token = $this->tokenFromIndex();
        $this->hydraReturns(fn () => $this->json(['client_id' => $this->hydraRequests[0]['body']['client_id'], 'client_secret' => 'hydra-echoed-secret'], 201));

        $this->client->request('POST', self::URL . '/new', ['_token' => $token]);

        self::assertCount(1, $this->hydraRequests);
        $sent = $this->hydraRequests[0];
        self::assertSame(['POST', '/admin/clients'], [$sent['method'], $sent['url']]);
        self::assertNotEmpty($sent['body']['client_secret']);
        $slug = $sent['body']['client_id'];
        self::assertResponseRedirects(sprintf('%s/%s/edit', self::URL, $slug));

        $client = self::hydraClient(['client_id' => $slug]);
        $this->hydraReturns($this->json($client), $this->json($client));

        $first = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(['hydra-echoed-secret'], $this->secretInputs($first));
        self::assertCount(0, $first->filter('form[action$="/regenerate-secret"]'));

        $second = $this->client->request('GET', sprintf('%s/%s/edit', self::URL, $slug));
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->secretInputs($second));
        self::assertStringNotContainsString('hydra-echoed-secret', (string) $this->client->getResponse()->getContent());
        self::assertCount(1, $second->filter('form[action$="/regenerate-secret"]'));
    }

    public function testCreateWhenHydraFailsGoesBackToTheIndexWithoutASecret(): void
    {
        $this->loginAdmin();
        $token = $this->tokenFromIndex();
        $this->hydraReturns($this->json(['error' => 'boom'], 500));

        $this->client->request('POST', self::URL . '/new', ['_token' => $token]);

        self::assertResponseRedirects(self::URL);
        $this->hydraReturns($this->json([]));
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->client->getCrawler()->filter('.alert-warning input')->count());
    }

    public function testCreateRejectsAMissingCsrfToken(): void
    {
        $this->loginAdmin();

        $this->client->request('POST', self::URL . '/new');

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->hydraRequests);
    }

    public function testEditUnknownClientIs404(): void
    {
        $this->loginAdmin();
        $this->hydraReturns($this->json(['error' => 'not_found'], 404));

        $this->client->request('GET', sprintf('%s/client-gone/edit', self::URL));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @dataProvider invalidEdits
     */
    public function testEditInvalidFormIs422AndNothingIsSent(string $field, string $value): void
    {
        $this->loginAdmin();
        $form = $this->editForm();
        $form[$field] = $value;
        $this->hydraReturns($this->json(self::hydraClient()));

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['GET'], array_column($this->hydraRequests, 'method'));
    }

    public static function invalidEdits(): iterable
    {
        yield 'empty name' => ['oidc_client[name]', ''];
        yield 'empty scopes' => ['oidc_client[scopes]', ''];
        yield 'bad redirect uri' => ['oidc_client[redirectUris][0]', 'not a url'];
    }

    public function testEditWhenHydraFailsIs502(): void
    {
        $this->loginAdmin();
        $form = $this->editForm();
        $form['oidc_client[name]'] = 'Wiki renamed';
        $this->hydraReturns($this->json(self::hydraClient()), $this->json(['error' => 'boom'], 500));

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(502);
        self::assertSame(['GET', 'PUT'], array_column($this->hydraRequests, 'method'));
    }

    public function testEditWhenHydraIsDownShowsTheUnavailablePage(): void
    {
        $this->loginAdmin();
        $this->hydraReturns($this->unreachable());

        $crawler = $this->client->request('GET', sprintf('%s/%s/edit', self::URL, self::SLUG));

        self::assertResponseStatusCodeSame(502);
        self::assertCount(1, $crawler->filter('[data-oidc-unavailable]'));
        self::assertCount(1, $crawler->filter(sprintf('[data-oidc-unavailable] a[href="%s"]', self::URL)), 'links back to the client list');
    }

    public function testRegenerateSecretWhenHydraIsDownShowsTheUnavailablePage(): void
    {
        $this->loginAdmin();
        $token = $this->editPageToken('regenerate-secret');
        $this->hydraReturns($this->unreachable());

        $crawler = $this->client->request('POST', sprintf('%s/%s/regenerate-secret', self::URL, self::SLUG), ['_token' => $token]);

        self::assertResponseStatusCodeSame(502);
        self::assertCount(1, $crawler->filter('[data-oidc-unavailable]'));
        self::assertSame(['GET'], array_column($this->hydraRequests, 'method'), 'no secret was sent');
    }

    public function testEditSendsTheFormAndKeepsFieldsTheFormDoesNotManage(): void
    {
        $this->loginAdmin();
        $form = $this->editForm();
        $form['oidc_client[name]'] = 'Wiki renamed';
        $form['oidc_client[redirectUris][0]'] = 'https://wiki.example.org/cb2';
        $form['oidc_client[logoUri]'] = '';
        $form['oidc_client[requiredFlag]'] = 'wiki_account';
        $this->hydraReturns($this->json(self::hydraClient()), $this->json(self::hydraClient()));

        $this->client->submit($form);

        self::assertResponseRedirects(self::URL);
        $put = $this->hydraRequests[1];
        self::assertSame(['PUT', '/admin/clients/' . self::SLUG], [$put['method'], $put['url']]);
        $body = $put['body'];
        self::assertSame('Wiki renamed', $body['client_name']);
        self::assertSame(['https://wiki.example.org/cb2'], $body['redirect_uris']);
        self::assertArrayNotHasKey('logo_uri', $body, 'an emptied field drops the old value');
        self::assertSame('wiki_account', $body['metadata']['required_flag']);
        // Not on the form: carried over from Hydra's copy.
        self::assertSame('client_secret_basic', $body['token_endpoint_auth_method']);
        self::assertTrue($body['skip_consent']);
        self::assertSame('1h', $body['access_token_lifetime']);
        self::assertSame('kept', $body['metadata']['other']);
        self::assertSame(['authorization_code', 'refresh_token', 'client_credentials'], $body['grant_types']);
        // Server-managed, must not be echoed back; the secret is never re-sent on a plain edit.
        self::assertArrayNotHasKey('created_at', $body);
        self::assertArrayNotHasKey('registration_access_token', $body);
        self::assertArrayNotHasKey('client_secret', $body);
    }

    public function testRegenerateSecretSendsANewSecretAndShowsItOnce(): void
    {
        $this->loginAdmin();
        $token = $this->editPageToken('regenerate-secret');
        $this->hydraReturns($this->json(self::hydraClient()), $this->json(self::hydraClient()));

        $this->client->request('POST', sprintf('%s/%s/regenerate-secret', self::URL, self::SLUG), ['_token' => $token]);

        self::assertResponseRedirects(sprintf('%s/%s/edit', self::URL, self::SLUG));
        $put = $this->hydraRequests[1];
        self::assertSame('PUT', $put['method']);
        $secret = $put['body']['client_secret'];
        self::assertNotEmpty($secret);
        self::assertTrue($put['body']['skip_consent'], 'the rest of the client is kept');
        self::assertSame('Wiki', $put['body']['client_name']);

        $this->hydraReturns($this->json(self::hydraClient()));
        $crawler = $this->client->followRedirect();
        self::assertSame([$secret], $this->secretInputs($crawler));
    }

    public function testRegenerateSecretWhenHydraFailsShowsNoSecret(): void
    {
        $this->loginAdmin();
        $token = $this->editPageToken('regenerate-secret');
        $this->hydraReturns($this->json(self::hydraClient()), $this->json(['error' => 'boom'], 500));

        $this->client->request('POST', sprintf('%s/%s/regenerate-secret', self::URL, self::SLUG), ['_token' => $token]);

        self::assertResponseRedirects(sprintf('%s/%s/edit', self::URL, self::SLUG));
        $this->hydraReturns($this->json(self::hydraClient()));
        $crawler = $this->client->followRedirect();
        self::assertSame([], $this->secretInputs($crawler));
        self::assertCount(1, $crawler->filter('form[action$="/regenerate-secret"]'));
    }

    public function testRegenerateSecretRejectsAMissingCsrfToken(): void
    {
        $this->loginAdmin();

        $this->client->request('POST', sprintf('%s/%s/regenerate-secret', self::URL, self::SLUG));

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->hydraRequests);
    }

    public function testDeleteRemovesTheClientInHydra(): void
    {
        $this->loginAdmin();
        $token = $this->editPageToken('delete');
        $this->hydraReturns(new MockResponse('', ['http_code' => 204]));

        $this->client->request('POST', sprintf('%s/%s/delete', self::URL, self::SLUG), ['_token' => $token]);

        self::assertResponseRedirects(self::URL);
        self::assertSame(['DELETE', '/admin/clients/' . self::SLUG], [$this->hydraRequests[0]['method'], $this->hydraRequests[0]['url']]);
    }

    public function testDeleteWhenHydraFailsGoesBackToTheClient(): void
    {
        $this->loginAdmin();
        $token = $this->editPageToken('delete');
        $this->hydraReturns($this->json(['error' => 'boom'], 500));

        $this->client->request('POST', sprintf('%s/%s/delete', self::URL, self::SLUG), ['_token' => $token]);

        self::assertResponseRedirects(sprintf('%s/%s/edit', self::URL, self::SLUG));
    }

    public function testDeleteRejectsAMissingCsrfToken(): void
    {
        $this->loginAdmin();

        $this->client->request('POST', sprintf('%s/%s/delete', self::URL, self::SLUG));

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->hydraRequests);
    }

    /**
     * A Hydra client with fields the form doesn't manage.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function hydraClient(array $overrides = []): array
    {
        return $overrides + [
            'client_id' => self::SLUG,
            'client_name' => 'Wiki',
            'redirect_uris' => ['https://wiki.example.org/cb'],
            'post_logout_redirect_uris' => [],
            'logo_uri' => 'https://wiki.example.org/logo.png',
            'scope' => 'openid profile email',
            'grant_types' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'client_secret_basic',
            'skip_consent' => true,
            'access_token_lifetime' => '1h',
            'metadata' => ['other' => 'kept'],
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
            'registration_access_token' => 'rat',
        ];
    }

    private function loginAdmin(): void
    {
        $this->client->loginUser($this->persistUser($this->client, 'oidc-page-admin', null, null, ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');
    }

    private function tokenFromIndex(): string
    {
        $this->hydraReturns($this->json([]));
        $crawler = $this->client->request('GET', self::URL);
        $this->hydraRequests = [];

        return (string) $crawler->filter(sprintf('form[action="%s/new"] input[name="_token"]', self::URL))->attr('value');
    }

    private function editPage(): Crawler
    {
        $this->hydraReturns($this->json(self::hydraClient()));
        $crawler = $this->client->request('GET', sprintf('%s/%s/edit', self::URL, self::SLUG));
        self::assertResponseIsSuccessful();
        $this->hydraRequests = [];

        return $crawler;
    }

    private function editForm(): \Symfony\Component\DomCrawler\Form
    {
        return $this->editPage()->filter('form[name="oidc_client"]')->form();
    }

    private function editPageToken(string $action): string
    {
        return (string) $this->editPage()
            ->filter(sprintf('form[action$="/%s/%s"] input[name="_token"]', self::SLUG, $action))
            ->attr('value');
    }

    /**
     * Values of the one-shot secret boxes on an edit page.
     *
     * @return list<string>
     */
    private function secretInputs(Crawler $crawler): array
    {
        return $crawler->filter('.alert-warning input[readonly]')->each(fn (Crawler $i) => (string) $i->attr('value'));
    }

    private function hydraReturns(MockResponse|\Closure ...$responses): void
    {
        foreach ($responses as $response) {
            $this->hydraResponses[] = $response;
        }
    }

    private function json(mixed $data, int $status = 200): MockResponse
    {
        return new MockResponse(
            json_encode($data, JSON_THROW_ON_ERROR),
            ['http_code' => $status, 'response_headers' => ['content-type' => 'application/json']],
        );
    }

    private function unreachable(): MockResponse
    {
        return new MockResponse('', ['error' => 'Connection refused']);
    }
}
