<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyAccountsPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Every test runs in a transaction rolled back at the end (the kernel is not rebooted between
 * requests of a functional test). Fixtures are random: tests create what they need through
 * TestCatalog.
 */
trait CompanyAccountsTestTrait
{
    private ?TestCatalog $testCatalog = null;

    private function beginTestTransaction(): void
    {
        $this->connection()->beginTransaction();
    }

    private function rollBackTestTransaction(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
        $this->testCatalog = null;
    }

    private function catalog(): TestCatalog
    {
        return $this->testCatalog ??= new TestCatalog(self::getContainer());
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function reload(object $entity): object
    {
        $this->entityManager()->clear();
        $id = $entity instanceof ResourceInterface ? $entity->getId() : null;
        $reloaded = $this->entityManager()->find($entity::class, $id);
        self::assertInstanceOf($entity::class, $reloaded);

        return $reloaded;
    }

    private function idOf(ResourceInterface $resource): int
    {
        $id = $resource->getId();
        self::assertIsInt($id);

        return $id;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
