<?php

namespace Vvbx\AuthBundle\Testing;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * IdP OIDC simulé (découverte, jeton, userinfo) pour les tests des apps.
 *
 * À brancher en env test : framework.http_client.mock_response_factory: Vvbx\AuthBundle\Testing\FakeIdp,
 * avec OIDC_ISSUER=https://idp.test. État statique : chaque test appelle reset() puis règle les claims renvoyés.
 */
final class FakeIdp
{
    public const ISSUER = 'https://idp.test';

    /** @var array<string, mixed> */
    public static array $claims = [];

    public static bool $tokenFails = false;

    /** @var list<array{method: string, url: string, body: string}> */
    public static array $requests = [];

    public static function reset(): void
    {
        self::$claims = [];
        self::$tokenFails = false;
        self::$requests = [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        self::$requests[] = ['method' => $method, 'url' => $url, 'body' => \is_string($options['body'] ?? null) ? $options['body'] : ''];

        return match ($url) {
            self::ISSUER.'/.well-known/openid-configuration' => self::json([
                'issuer' => self::ISSUER,
                'authorization_endpoint' => self::ISSUER.'/oauth/v2/authorize',
                'token_endpoint' => self::ISSUER.'/oauth/v2/token',
                'userinfo_endpoint' => self::ISSUER.'/oidc/v1/userinfo',
            ]),
            self::ISSUER.'/oauth/v2/token' => self::$tokenFails
                ? self::json(['error' => 'invalid_grant'], 400)
                : self::json(['access_token' => 'access-token', 'token_type' => 'Bearer']),
            self::ISSUER.'/oidc/v1/userinfo' => self::json(self::$claims),
            default => new MockResponse('', ['http_code' => 404]),
        };
    }

    /**
     * Corps de la demande de jeton envoyée à l'IdP (dernière en date), pour vérifier le code_verifier.
     *
     * @return array<string, mixed>
     */
    public static function lastTokenRequest(): array
    {
        $tokens = array_values(array_filter(self::$requests, static fn (array $r): bool => str_ends_with($r['url'], '/oauth/v2/token')));
        if ([] === $tokens) {
            return [];
        }
        parse_str($tokens[\count($tokens) - 1]['body'], $body);

        /* @var array<string, mixed> $body */
        return $body;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse((string) json_encode($data), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
