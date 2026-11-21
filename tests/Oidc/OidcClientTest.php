<?php

namespace Vvbx\AuthBundle\Tests\Oidc;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Vvbx\AuthBundle\Oidc\OidcClient;
use Vvbx\AuthBundle\Oidc\OidcException;
use Vvbx\AuthBundle\Testing\FakeIdp;

final class OidcClientTest extends TestCase
{
    protected function setUp(): void
    {
        FakeIdp::reset();
    }

    public function testDisabledWithoutIssuerOrClientId(): void
    {
        self::assertFalse($this->oidc('', 'app')->isEnabled());
        self::assertFalse($this->oidc(FakeIdp::ISSUER, '')->isEnabled());
        self::assertTrue($this->oidc(FakeIdp::ISSUER, 'app')->isEnabled());
    }

    public function testCodeChallengeFollowsRfc7636(): void
    {
        // ---- exemple de l'annexe B de la RFC 7636
        self::assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', OidcClient::codeChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', OidcClient::newCodeVerifier());
    }

    public function testAuthorizationUrlCarriesPkceChallenge(): void
    {
        $verifier = OidcClient::newCodeVerifier();
        $url = $this->oidc(FakeIdp::ISSUER, 'app', new MockHttpClient(new FakeIdp()))->authorizationUrl('https://app.test/oidc/callback', 'state-1', $verifier);

        self::assertStringStartsWith(FakeIdp::ISSUER.'/oauth/v2/authorize?', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame('app', $query['client_id']);
        self::assertSame('code', $query['response_type']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame(OidcClient::codeChallenge($verifier), $query['code_challenge']);
        self::assertSame('state-1', $query['state']);
    }

    public function testFetchClaimsSendsVerifierAndReadsUserinfo(): void
    {
        FakeIdp::$claims = ['sub' => 'sub-1', 'email' => 'a@exemple.fr', 'email_verified' => true];

        $claims = $this->oidc(FakeIdp::ISSUER, 'app', new MockHttpClient(new FakeIdp()))->fetchClaims('code-1', 'https://app.test/oidc/callback', 'verifier-1');

        self::assertSame('sub-1', $claims['sub']);
        self::assertSame('verifier-1', FakeIdp::lastTokenRequest()['code_verifier']);
        self::assertSame('code-1', FakeIdp::lastTokenRequest()['code']);
    }

    public function testTokenErrorBecomesOidcException(): void
    {
        FakeIdp::$tokenFails = true;

        $this->expectException(OidcException::class);
        $this->oidc(FakeIdp::ISSUER, 'app', new MockHttpClient(new FakeIdp()))->fetchClaims('code-1', 'https://app.test/oidc/callback', 'verifier-1');
    }

    public function testDiscoveryAnnouncingAnotherIssuerIsRejected(): void
    {
        $oidc = $this->oidc(FakeIdp::ISSUER, 'app', new MockHttpClient(new MockResponse((string) json_encode([
            'issuer' => 'https://autre.test',
            'authorization_endpoint' => 'https://autre.test/authorize',
        ]))));

        $this->expectException(OidcException::class);
        $oidc->authorizationUrl('https://app.test/oidc/callback', 'state', OidcClient::newCodeVerifier());
    }

    private function oidc(string $issuer, string $clientId, ?MockHttpClient $http = null): OidcClient
    {
        return new OidcClient($http ?? new MockHttpClient(), new ArrayAdapter(), $issuer, $clientId, 'secret');
    }
}
