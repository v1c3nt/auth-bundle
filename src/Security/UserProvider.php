<?php

namespace Vvbx\SuiteBundle\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Vvbx\SuiteBundle\Doctrine\UserRepository;

/**
 * Comptes chargés par e-mail. Au rechargement de la session (à chaque requête, même SELECT qu'un provider entity),
 * un compte désactivé est traité comme introuvable : la session tombe à la requête suivante.
 *
 * @implements UserProviderInterface<UserInterface>
 */
final class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->users->findOneByEmail($identifier) ?? throw self::notFound($identifier);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$this->supportsClass($user::class)) {
            throw new UnsupportedUserException(sprintf('Compte de type "%s" non géré.', $user::class));
        }

        $fresh = $this->users->reload($user);
        if (null === $fresh || ($fresh instanceof ActivatableUserInterface && !$fresh->isActive())) {
            throw self::notFound($user->getUserIdentifier());
        }

        return $fresh;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, $this->users->userClass(), true);
    }

    // ---- rehachage transparent du mot de passe à la connexion, délégué au repository de l'app s'il le sait faire
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $repository = $this->users->em()->getRepository($this->users->userClass());
        if ($repository instanceof PasswordUpgraderInterface) {
            $repository->upgradePassword($user, $newHashedPassword);
        }
    }

    private static function notFound(string $identifier): UserNotFoundException
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier($identifier);

        return $exception;
    }
}
