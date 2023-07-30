<?php

declare(strict_types=1);

namespace CliTube\Bridge\Spiral\Tests\Cycle;

use CliTube\Bridge\Spiral\Tests\Cycle\Traits\Loggable;
use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Database;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseManager;
use Cycle\Database\Driver\DriverInterface;
use Cycle\Database\Driver\Handler;
use Cycle\Database\Driver\SQLite\SQLiteDriver;
use Cycle\ORM\Collection\ArrayCollectionFactory;
use Cycle\ORM\Config\RelationConfig;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\SchemaInterface;
use Cycle\ORM\Transaction\UnitOfWork;
use PHPUnit\Framework\TestCase;

abstract class BaseCycle extends TestCase
{
    use Loggable;

    protected ?DatabaseManager $dbal = null;
    protected static ?DriverInterface $driver = null;
    protected ?ORM $orm = null;

    public function setUp(): void
    {
        if (\filter_var(\getenv('DEBUG') ?? false, FILTER_VALIDATE_BOOL)) {
            $this->enableProfiling();
        }
        $this->dbal = new DatabaseManager(new DatabaseConfig());
        $this->dbal->addDatabase(
            new Database(
                'default',
                '',
                $this->getDriver()
            )
        );
    }

    public function tearDown(): void
    {
        $this->dropDatabase($this->dbal->database('default'));
        $this->dbal = null;
        $this->orm = null;
        if (\function_exists('gc_collect_cycles')) {
            \gc_collect_cycles();
        }
    }

    public function withSchema(SchemaInterface $schema): ORM
    {
        $this->orm = new ORM(
            new Factory(
                $this->dbal,
                RelationConfig::getDefault(),
                null,
                new ArrayCollectionFactory()
            ),
            $schema,
        );

        return $this->orm;
    }

    protected function save(object ...$entities): void
    {
        $tr = new UnitOfWork($this->orm);
        foreach ($entities as $entity) {
            $tr->persistDeferred($entity);
        }
        $tr->run();
    }

    protected function getDriver(): DriverInterface
    {
        self::$driver ??= SQLiteDriver::create(new SQLiteDriverConfig(
            connection: new MemoryConnectionConfig(),
            queryCache: true,
        ));

        return self::$driver;
    }

    protected function getDatabase(): Database
    {
        return $this->dbal->database('default');
    }

    protected function dropDatabase(DatabaseInterface $database = null): void
    {
        if ($database === null) {
            return;
        }

        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();

            foreach ($schema->getForeignKeys() as $foreign) {
                $schema->dropForeignKey($foreign->getColumns());
            }

            $schema->save(Handler::DROP_FOREIGN_KEYS);
        }

        foreach ($database->getTables() as $table) {
            $schema = $table->getSchema();
            $schema->declareDropped();
            $schema->save();
        }
    }
}
