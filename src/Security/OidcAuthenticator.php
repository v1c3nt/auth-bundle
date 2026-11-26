<?php

namespace Vvbx\SuiteBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Vvbx\SuiteBundle\Oidc\OidcClient;
use Vvbx\SuiteBundle\Oidc\OidcException;

/**
 * Retour de l'IdP sur /oidc/callback : vérifie le state, lit les claims et retrouve le compte.
 *
 * L'utilisateur est trouvé par OidcUserResolverInterface (comptes de l'app par défaut : AccountUserResolver).
 */
final class OidcAuthenticator extends AbstractAuthenticator
{
    public const SESSION_KEY = 'vvbx_suite.oidc.pending';
    public const CALLBACK_ROUTE = 'vvbx_suite_oidc_callback';

    public function __construct(
        private readonly OidcClient $oidc,
        private readonly OidcUserResolverInterface $users,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $targetRoute,
        private readonly string $loginRoute,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->oidc->isEnabled() && self::CALLBACK_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        // ---- state et verifier à usage unique : retirés de la session quel que soit le résultat
        $pending = $request->getSession()->remove(self::SESSION_KEY);
        $state = $request->query->getString('state');
        if (!\is_array($pending) || !\is_string($pending['state'] ?? null) || !\is_string($pending['verifier'] ?? null)
            || '' === $state || !hash_equals($pending['state'], $state)) {
            throw new CustomUserMessageAuthenticationException('oidc.error.state');
        }

        $code = $request->query->getString('code');
        if ($request->query->has('error') || '' === $code) {
            throw new CustomUserMessageAuthenticationException('oidc.error.denied');
        }

        try {
            $claims = $this->oidc->fetchClaims($code, $this->redirectUri(), $pending['verifier']);
        } catch (OidcException $e) {
            throw new CustomUserMessageAuthenticationException('oidc.error.idp', [], 0, $e);
        }

        $subject = $claims['sub'] ?? null;
        if (!\is_string($subject) || '' === $subject) {
            throw new CustomUserMessageAuthenticationException('oidc.error.idp');
        }

        return new SelfValidatingPassport(new UserBadge($subject, fn () => $this->users->resolve($subject, $claims)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        return new RedirectResponse($this->urls->generate($this->targetRoute));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urls->generate($this->loginRoute));
    }

    public function redirectUri(): string
    {
        return $this->urls->generate(self::CALLBACK_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
