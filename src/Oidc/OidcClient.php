<?php

namespace Vvbx\AuthBundle\Oidc;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client OIDC minimal (code + PKCE) vers l'IdP de la suite.
 *
 * OIDC standard uniquement : endpoints lus dans la découverte, claims sub, email et email_verified.
 * Désactivé tant que OIDC_ISSUER ou OIDC_CLIENT_ID est vide. OIDC_EXTRA_SCOPES (séparés par des espaces) s'ajoute
 * aux scopes demandés : le déploiement y met ce que son IdP attend (ex. l'organisation du client), l'app n'en sait rien.
 */
final class OidcClient
{
    private const SCOPE = 'openid email';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        #[Autowire(env: 'default::OIDC_ISSUER')]
        private readonly ?string $issuer,
        #[Autowire(env: 'default::OIDC_CLIENT_ID')]
        private readonly ?string $clientId,
        #[Autowire(env: 'default::OIDC_CLIENT_SECRET')]
        private readonly ?string $clientSecret,
        #[Autowire(env: 'default::OIDC_EXTRA_SCOPES')]
        private readonly ?string $extraScopes = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== (string) $this->issuer && '' !== (string) $this->clientId;
    }

    public function authorizationUrl(string $redirectUri, string $state, string $codeVerifier): string
    {
        return $this->endpoint('authorization_endpoint').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $this->scope(),
            'state' => $state,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Échange le code contre un jeton d'accès puis lit les claims sur userinfo.
     *
     * @return array<string, mixed>
     *
     * @throws OidcException
     */
    public function fetchClaims(string $code, string $redirectUri, string $codeVerifier): array
    {
        try {
            $tokens = $this->httpClient->request('POST', $this->endpoint('token_endpoint'), [
                'auth_basic' => [(string) $this->clientId, (string) $this->clientSecret],
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => $codeVerifier,
                ],
            ])->toArray();

            $accessToken = $tokens['access_token'] ?? null;
            if (!\is_string($accessToken) || '' === $accessToken) {
                throw new OidcException('Réponse du jeton sans access_token.');
            }

            // ---- claims lus sur userinfo, appelé en direct sur l'IdP : pas besoin de vérifier la signature de l'id_token
            return $this->httpClient->request('GET', $this->endpoint('userinfo_endpoint'), [
                'auth_bearer' => $accessToken,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw new OidcException('Échange avec l\'IdP impossible : '.$e->getMessage(), previous: $e);
        }
    }

    // ---- openid email, puis les scopes supplémentaires du déploiement, sans doublon
    public function scope(): string
    {
        $extra = preg_split('/\s+/', trim((string) $this->extraScopes), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_values(array_unique([...explode(' ', self::SCOPE), ...$extra])));
    }

    public static function newCodeVerifier(): string
    {
        return self::base64Url(random_bytes(32));
    }

    public static function codeChallenge(string $codeVerifier): string
    {
        return self::base64Url(hash('sha256', $codeVerifier, true));
    }

    private function endpoint(string $name): string
    {
        $issuer = rtrim((string) $this->issuer, '/');

        try {
            /** @var array<string, mixed> $configuration */
            $configuration = $this->cache->get('oidc_discovery_'.md5($issuer), function (ItemInterface $item) use ($issuer): array {
                $item->expiresAfter(3600);

                return $this->httpClient->request('GET', $issuer.'/.well-known/openid-configuration')->toArray();
            });
        } catch (ExceptionInterface $e) {
            throw new OidcException('Découverte OIDC impossible : '.$e->getMessage(), previous: $e);
        }

        // ---- la découverte doit annoncer l'issuer configuré, sinon on parle au mauvais IdP
        if (rtrim((string) ($configuration['issuer'] ?? ''), '/') !== $issuer) {
            throw new OidcException('La découverte OIDC annonce un autre issuer.');
        }

        $endpoint = $configuration[$name] ?? null;
        if (!\is_string($endpoint) || '' === $endpoint) {
            throw new OidcException(sprintf('Découverte OIDC sans %s.', $name));
        }

        return $endpoint;
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
