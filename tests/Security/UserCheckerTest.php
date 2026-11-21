<?php

namespace Vvbx\AuthBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Vvbx\AuthBundle\Security\ActivatableUserInterface;
use Vvbx\AuthBundle\Security\UserChecker;

final class UserCheckerTest extends TestCase
{
    public function testInactiveAccountIsRefused(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('user.inactive');

        (new UserChecker())->checkPreAuth($this->account(false));
    }

    public function testActiveAccountAndPlainUserPass(): void
    {
        $checker = new UserChecker();
        $checker->checkPreAuth($this->account(true));
        $checker->checkPreAuth(new InMemoryUser('a@exemple.fr', null));

        $this->addToAssertionCount(2);
    }

    private function account(bool $active): ActivatableUserInterface
    {
        return new class($active) implements ActivatableUserInterface {
            public function __construct(private bool $active)
            {
            }

            public function isActive(): bool
            {
                return $this->active;
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'a@exemple.fr';
            }
        };
    }
}
