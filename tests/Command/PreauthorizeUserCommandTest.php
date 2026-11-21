<?php

namespace Vvbx\AuthBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Vvbx\AuthBundle\Account\AccountException;
use Vvbx\AuthBundle\Account\AccountManagerInterface;
use Vvbx\AuthBundle\Command\PreauthorizeUserCommand;

final class PreauthorizeUserCommandTest extends TestCase
{
    public function testDelegatesToTheApp(): void
    {
        $accounts = $this->accounts();
        $tester = new CommandTester(new PreauthorizeUserCommand($accounts));

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'a@exemple.fr', 'role' => 'ROLE_ADMIN']));
        self::assertStringContainsString('pré-autorisé', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'a@exemple.fr', 'role' => 'ROLE_ADMIN']));
        self::assertStringContainsString('déjà présent', $tester->getDisplay());
        self::assertSame([['a@exemple.fr', 'ROLE_ADMIN'], ['a@exemple.fr', 'ROLE_ADMIN']], $accounts->calls);
    }

    public function testUnknownRoleIsRejectedBeforeTheApp(): void
    {
        $accounts = $this->accounts();
        $tester = new CommandTester(new PreauthorizeUserCommand($accounts));

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'a@exemple.fr', 'role' => 'ROLE_SUPER']));
        self::assertSame([], $accounts->calls);
    }

    public function testAppRuleIsReported(): void
    {
        $tester = new CommandTester(new PreauthorizeUserCommand($this->accounts(refuse: true)));

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'a@exemple.fr', 'role' => 'ROLE_ADMIN']));
        self::assertStringContainsString('Trois administrateurs au maximum', $tester->getDisplay());
    }

    private function accounts(bool $refuse = false): AccountManagerInterface&\stdClass
    {
        return new class($refuse) extends \stdClass implements AccountManagerInterface {
            /** @var list<array{string, string}> */
            public array $calls = [];

            /** @var array<string, true> */
            private array $known = [];

            public function __construct(private bool $refuse)
            {
            }

            public function roles(): array
            {
                return ['ROLE_ADMIN', 'ROLE_MANAGER'];
            }

            public function preauthorize(string $email, string $role): bool
            {
                if ($this->refuse) {
                    throw new AccountException('Trois administrateurs au maximum.');
                }
                $this->calls[] = [$email, $role];
                $created = !isset($this->known[$email]);
                $this->known[$email] = true;

                return $created;
            }
        };
    }
}
