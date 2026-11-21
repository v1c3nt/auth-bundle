<?php

namespace Vvbx\AuthBundle\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vvbx\AuthBundle\Oidc\OidcClient;
use Vvbx\AuthBundle\Security\OidcAuthenticator;

/**
 * Routes /oidc/login et /oidc/callback, importées par l'app depuis @VvbxAuthBundle/config/routes.php.
 * Sans OIDC_*, les deux répondent 404.
 */
final class OidcController
{
    public function __construct(
        private readonly OidcClient $oidc,
        private readonly OidcAuthenticator $authenticator,
    ) {
    }

    public function login(Request $request): Response
    {
        if (!$this->oidc->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $state = bin2hex(random_bytes(16));
        $verifier = OidcClient::newCodeVerifier();
        $request->getSession()->set(OidcAuthenticator::SESSION_KEY, ['state' => $state, 'verifier' => $verifier]);

        return new RedirectResponse($this->oidc->authorizationUrl($this->authenticator->redirectUri(), $state, $verifier));
    }

    // ---- traité par OidcAuthenticator quand le SSO est actif ; sinon la route n'existe pas
    public function callback(): never
    {
        throw new NotFoundHttpException();
    }
}
