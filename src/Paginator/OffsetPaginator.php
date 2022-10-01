<?php

declare(strict_types=1);

namespace CliTube\Bridge\Spiral\Paginator;

use Closure;
use Countable;
use IteratorAggregate;
use Spiral\Pagination\PaginableInterface;
use Traversable;

/**
 * Paginator Bridge from Spiral to CliTube
 *
 * @template TPaginator of PaginableInterface&IteratorAggregate&Countable
 * @template TItemType
 */
class OffsetPaginator implements \CliTube\Contract\Pagination\OffsetPaginator
{
    private int $limit = 1;
    private int $offset = 0;
    private int $page = 1;
    private ?int $pages = null;
    private ?int $count = null;

    private ?array $buffer = null;

    /** @var null|Closure(TItemType $item): array<array-key, scalar> */
    private ?Closure $itemConverter = null;
    /** @var null|Closure(TPaginator $paginator): ?int */
    private ?Closure $countCalculator = null;

    private function __construct(
        private PaginableInterface&IteratorAggregate&Countable $paginator,
    ) {
    }

    public static function create(PaginableInterface&IteratorAggregate&Countable $paginator): static
    {
        $paginator = clone $paginator;
        return new static($paginator);
    }

    /**
     * Set closure that will convert items from source.
     *
     * @param Closure(TItemType $item): array<array-key, scalar> $closure
     */
    public function withItemConverter(Closure $closure): static
    {
        $clone = clone $this;
        $clone->itemConverter = $closure;
        return $clone;
    }

    /**
     * Set function to calc all items.
     *
     * @param Closure(TPaginator $paginator): ?int $closure
     */
    public function withCountCalculator(Closure $closure): static
    {
        $clone = clone $this;
        $clone->countCalculator = $closure;
        return $clone;
    }

    public function getIterator(): Traversable
    {
        if ($this->itemConverter !== null) {
            foreach ($this->getContent() as $item) {
                yield ($this->itemConverter)($item);
            }
        } else {
            yield from $this->getContent();
        }
    }

    public function withOffset(int $offset): static
    {
        $clone = clone $this;
        $clone->paginator->offset($offset);
        $clone->offset = $offset;
        $clone->calc();
        return $clone;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function withLimit(int $limit): static
    {
        $clone = clone $this;
        $clone->paginator->limit($limit);
        $clone->limit = $limit;
        $clone->calc();
        return $clone;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function nextPage(): static
    {
        $clone = clone $this;
        $clone->offset = $clone->pages === null
            ? $clone->offset + $clone->limit
            : \min(($clone->pages - 1) * $clone->limit, $clone->limit * $clone->page);
        $clone->calc();
        return $clone;
    }

    public function previousPage(): static
    {
        $clone = clone $this;
        $clone->offset = \max(0, $clone->limit * ($clone->page - 2));
        $clone->calc();
        return $clone;
    }

    public function getCount(): ?int
    {
        if ($this->count === null && $this->countCalculator !== null) {
            $this->count = ($this->countCalculator)(clone $this->paginator);
        }
        return $this->count;
    }

    public function count(): int
    {
        return \count($this->getContent());
    }

    public function __clone(): void
    {
        $this->paginator = clone $this->paginator;
    }

    private function calc(): void
    {
        $this->buffer = null;
        if ($this->count !== null) {
            $this->pages = \max(1, (int)\ceil($this->count / $this->limit));
        }
        $this->page = (int)\ceil($this->offset / $this->limit) + 1;
    }

    private function getContent(): array
    {
        $this->buffer ??= \iterator_to_array($this->paginator->getIterator(), false);
        return $this->buffer;
    }
}
