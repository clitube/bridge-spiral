<?php

declare(strict_types=1);

namespace CliTube\Bridge\Spiral\Paginator;

use CliTube\Support\Pagination\BaseOffsetPaginator;
use Closure;
use Countable;
use IteratorAggregate;
use Spiral\Pagination\PaginableInterface;
use Traversable;

/**
 * Paginator Bridge from Spiral to CliTube
 *
 * @template TPaginator of Countable&IteratorAggregate&PaginableInterface
 * @template TItemType
 */
final class OffsetPaginator extends BaseOffsetPaginator
{
    /** @var null|Closure(TItemType $item): array<array-key, scalar> */
    private ?Closure $itemConverter = null;

    /** @var null|Closure(TPaginator $paginator): ?int */
    private ?Closure $countCalculator = null;

    private function __construct(
        private Countable&IteratorAggregate&PaginableInterface $paginator,
    ) {
    }

    public function __clone()
    {
        $this->buffer = null;
        $this->paginator = clone $this->paginator;
    }

    public static function create(
        PaginableInterface&IteratorAggregate&Countable $paginator,
        bool $useDefaultCounter = true,
    ): self {
        $paginator = clone $paginator;
        $offsetPaginator = new self($paginator);

        return $useDefaultCounter
            ? $offsetPaginator->withCountCalculator(\count(...))
            : $offsetPaginator;
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
            return;
        }

        yield from $this->getContent();
    }

    public function withOffset(int $offset): static
    {
        $clone = parent::withOffset($offset);
        $clone->paginator->offset($offset);
        return $clone;
    }

    public function withLimit(int $limit): static
    {
        $clone = parent::withLimit($limit);
        $clone->paginator->limit($limit);
        return $clone;
    }

    public function getCount(): ?int
    {
        if ($this->count === null && $this->countCalculator !== null) {
            $this->count = ($this->countCalculator)(clone $this->paginator);
        }

        return parent::getCount();
    }

    protected function getContent(): array
    {
        $this->buffer ??= \iterator_to_array(
            $this->paginator->limit($this->limit)->offset($this->offset)->getIterator(),
            false,
        );
        return $this->buffer;
    }
}
