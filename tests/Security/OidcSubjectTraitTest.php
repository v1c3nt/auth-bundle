<?php

namespace Vvbx\SuiteBundle\Tests\Security;

use Doctrine\ORM\Mapping\Column;
use PHPUnit\Framework\TestCase;
use Vvbx\SuiteBundle\Security\OidcSubjectTrait;
use Vvbx\SuiteBundle\Security\OidcUserInterface;

final class OidcSubjectTraitTest extends TestCase
{
    public function testSubjectIsANullableMappedColumn(): void
    {
        $user = new TraitUser();
        self::assertNull($user->getOidcSubject());
        self::assertSame('sub-1', $user->setOidcSubject('sub-1')->getOidcSubject());

        $column = (new \ReflectionProperty(TraitUser::class, 'oidcSubject'))->getAttributes(Column::class)[0]->newInstance();
        self::assertTrue($column->nullable);
        self::assertSame(255, $column->length);
    }
}

final class TraitUser implements OidcUserInterface
{
    use OidcSubjectTrait;

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
}
