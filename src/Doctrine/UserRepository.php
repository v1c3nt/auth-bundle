<?php

namespace Vvbx\AuthBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;
use Vvbx\AuthBundle\Security\OidcUserInterface;

/**
 * Accès aux comptes de l'app (entité configurée par vvbx_auth.user_class), sans connaître son repository.
 */
final class UserRepository
{
    /**
     * @param class-string<UserInterface> $userClass
     */
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly string $userClass,
        private readonly string $emailField,
    ) {
    }

    /**
     * @return class-string<UserInterface>
     */
    public function userClass(): string
    {
        return $this->userClass;
    }

    public function findOneByEmail(string $email): ?UserInterface
    {
        return $this->em()->getRepository($this->userClass)->findOneBy([$this->emailField => $email]);
    }

    public function findOneBySubject(string $subject): ?OidcUserInterface
    {
        $user = $this->em()->getRepository($this->userClass)->findOneBy(['oidcSubject' => $subject]);

        return $user instanceof OidcUserInterface ? $user : null;
    }

    /**
     * Version à jour en base d'un compte venu de la session (même SELECT par clé primaire qu'un provider entity).
     */
    public function reload(UserInterface $user): ?UserInterface
    {
        $em = $this->em();
        $id = $em->getClassMetadata($this->userClass)->getIdentifierValues($user);
        if ([] === $id || \in_array(null, $id, true)) {
            return null;
        }

        return $em->find($this->userClass, $id);
    }

    public function flush(): void
    {
        $this->em()->flush();
    }

    public function em(): EntityManagerInterface
    {
        $em = $this->registry->getManagerForClass($this->userClass);
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('Aucun entity manager Doctrine ORM pour "%s".', $this->userClass));
        }

        return $em;
    }
}
