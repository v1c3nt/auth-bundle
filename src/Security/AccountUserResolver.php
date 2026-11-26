<?php

namespace Vvbx\SuiteBundle\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Vvbx\SuiteBundle\Doctrine\UserRepository;

/**
 * Comptes de l'app : le compte est rattaché au sub. À la première connexion, un compte existant sans sub est
 * rattaché par son e-mail vérifié (pré-autorisation par l'administrateur). Un inconnu n'a aucun accès.
 */
final class AccountUserResolver implements OidcUserResolverInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function resolve(string $subject, array $claims): OidcUserInterface
    {
        $user = $this->users->findOneBySubject($subject);
        if (null !== $user) {
            return $user;
        }

        $email = $claims['email'] ?? null;
        if (\is_string($email) && true !== ($claims['email_verified'] ?? false)) {
            // ---- ne dit rien des comptes de l'app (aucune recherche par e-mail) : seulement ce que l'IdP a déclaré
            throw new CustomUserMessageAuthenticationException('oidc.error.email_unverified');
        }

        if (\is_string($email)) {
            $user = $this->users->findOneByEmail($email);
            // ---- jamais de rattachement d'un compte déjà lié à un autre sub
            if ($user instanceof OidcUserInterface && null === $user->getOidcSubject()) {
                $user->setOidcSubject($subject);
                $this->users->flush();

                return $user;
            }
        }

        throw new CustomUserMessageAuthenticationException('oidc.error.unknown_user');
    }
}
