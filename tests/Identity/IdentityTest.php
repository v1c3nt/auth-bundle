<?php

namespace Vvbx\SuiteBundle\Tests\Identity;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vvbx\SuiteBundle\Identity\Identity;

final class IdentityTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testCompleteIdentityIsValid(): void
    {
        self::assertCount(0, $this->validator->validate(self::complete()));
    }

    public function testRequiredFields(): void
    {
        $violations = $this->validator->validate(new Identity());

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }
        sort($paths);

        self::assertSame(['city', 'email', 'name', 'postalCode', 'street'], $paths);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'code postal trop court' => ['setPostalCode', '3800', 'identity.postal_code.invalid'];
        yield 'SIREN à 8 chiffres' => ['setSiren', '12345678', 'identity.siren.invalid'];
        yield 'RNA sans W' => ['setRna', '123456789', 'identity.rna.invalid'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidFormats(string $setter, string $value, string $message): void
    {
        $identity = self::complete()->{$setter}($value);

        $violations = $this->validator->validate($identity);

        self::assertCount(1, $violations);
        self::assertSame($message, $violations[0]->getMessageTemplate());
    }

    public function testValuesAreNormalized(): void
    {
        $identity = (new Identity())
            ->setName('  Les Amis des Bêtes ')
            ->setShortName('')
            ->setSiren('123 456 789')
            ->setRna(' w123 456 789')
            ->setPostalCode('38 000');

        self::assertSame('Les Amis des Bêtes', $identity->getName());
        self::assertNull($identity->getShortName());
        self::assertSame('123456789', $identity->getSiren());
        self::assertSame('W123456789', $identity->getRna());
        self::assertSame('38000', $identity->getPostalCode());
    }

    public function testDisplayNamePrefersShortName(): void
    {
        $identity = (new Identity())->setName('Association Les Amis des Bêtes');
        self::assertSame('Association Les Amis des Bêtes', $identity->getDisplayName());

        $identity->setShortName('Les Amis des Bêtes');
        self::assertSame('Les Amis des Bêtes', $identity->getDisplayName());
    }

    public function testPostalAddress(): void
    {
        self::assertSame("12 rue des Lilas\nBât. B\n38000 Grenoble", self::complete()->getPostalAddress());
        self::assertSame('38000 Grenoble', (new Identity())->setPostalCode('38000')->setCity('Grenoble')->getPostalAddress());
        self::assertSame('', (new Identity())->getPostalAddress());
    }

    public function testDoctrineMapping(): void
    {
        $metadata = new ClassMetadata(Identity::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        (new AttributeDriver([\dirname(__DIR__, 2).'/src/Identity']))->loadMetadataForClass(Identity::class, $metadata);

        self::assertTrue($metadata->isEmbeddedClass);
        self::assertSame(
            ['name', 'shortName', 'acronym', 'street', 'postalCode', 'city', 'email', 'phone', 'website', 'siren', 'rna'],
            $metadata->getFieldNames(),
        );
        foreach ($metadata->getFieldNames() as $field) {
            self::assertTrue($metadata->isNullable($field), $field.' doit être nullable : une installation neuve démarre vide');
        }
    }

    private static function complete(): Identity
    {
        return (new Identity())
            ->setName('Association Les Amis des Bêtes')
            ->setStreet("12 rue des Lilas\nBât. B")
            ->setPostalCode('38000')
            ->setCity('Grenoble')
            ->setEmail('contact@amis-des-betes.fr')
            ->setWebsite('https://amis-des-betes.fr')
            ->setSiren('123456789')
            ->setRna('W123456789');
    }
}
