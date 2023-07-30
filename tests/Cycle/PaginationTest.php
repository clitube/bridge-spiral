<?php

declare(strict_types=1);

namespace CliTube\Bridge\Spiral\Tests\Cycle;

use CliTube\Bridge\Spiral\Paginator\OffsetPaginator;
use CliTube\Bridge\Spiral\Tests\Cycle\Traits\TableTrait;
use Cycle\ORM\Mapper\ClasslessMapper;
use Cycle\ORM\Mapper\StdMapper;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use DateTimeImmutable;

final class PaginationTest extends BaseCycle
{
    use TableTrait;

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTable('user', [
            'id' => 'primary',
            'name' => 'string',
            'email' => 'string',
        ]);

        $this->getDatabase()->table('user')->insertMultiple(
            ['name', 'email'],
            [
                ['name' => 'John Doe', 'email' => 'john@foo.bar'],
                ['name' => 'Jack Sparrow', 'email' => 'sparrow@carribian.com'],
                ['name' => 'John Wick', 'email' => 'john-wick@dog.yo'],
                ['name' => 'John McClane', 'email' => 'mc@time.go'],
                ['name' => 'John Rambo', 'email' => 'bow@boom'],
                ['name' => 'John Connor', 'email' => 't@800'],
                ['name' => 'John Snow', 'email' => 'dead@wall'],
                ['name' => 'John Smith', 'email' => 'smith@matrix'],
                ['name' => 'John Malkovich', 'email' => 'yet-another@john'],
                ['name' => 'John Travolta', 'email' => 'where-is-my-face@john'],
            ],
        );
    }

    public function testPaginator(): void
    {
        $this->initOrm(mapper: ClasslessMapper::class);
        $select = $this->orm->getRepository('user')->select()->orderBy('id');
        $paginator = OffsetPaginator::create($select)->withLimit(3);

        $this->assertSame(10, $paginator->getCount());
        $this->assertSame(3, $paginator->count());
        $values = \iterator_to_array($paginator);
        $this->assertSame('John Doe', $values[0]->name);

        $page2 = $paginator->nextPage();
        $this->assertCount(3, $page2);
        $values = \iterator_to_array($page2);
        $this->assertSame('John McClane', $values[0]->name);

        $page3 = $page2->nextPage();
        $this->assertCount(3, $page3);
        $values = \iterator_to_array($page3);
        $this->assertSame('John Snow', $values[0]->name);

        $page4 = $page3->nextPage();
        $this->assertCount(1, $page4);
        $values = \iterator_to_array($page4);
        $this->assertSame('John Travolta', $values[0]->name);

        $this->assertCount(1, $paginator->withOffset(9));
    }

    public function testConverter(): void
    {
        $this->initOrm(mapper: StdMapper::class);
        $select = $this->orm->getRepository('user')->select()->orderBy('id');
        $paginator = OffsetPaginator::create($select)
            ->withLimit(10)
            ->withItemConverter(
                fn(\stdClass $entity): array => ['name' => $entity->name, 'now' => new DateTimeImmutable()]
            );

        $this->assertSame(10, $paginator->getCount());
        $this->assertSame(10, $paginator->count());
        $values = \iterator_to_array($paginator);
        $this->assertIsArray($values[0]);
        $this->assertSame('John Doe', $values[0]['name']);
        $this->assertInstanceOf(DateTimeImmutable::class, $values[0]['now']);
    }

    private function initOrm(string $mapper = ClasslessMapper::class): void
    {
        $this->withSchema(new Schema([
            'user' => [
                SchemaInterface::MAPPER => $mapper,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'user',
                SchemaInterface::PRIMARY_KEY => 'id',
                SchemaInterface::COLUMNS => ['id', 'name', 'email'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
            ],
        ]));
    }
}
