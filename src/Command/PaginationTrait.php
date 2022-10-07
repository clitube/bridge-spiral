<?php

declare(strict_types=1);

namespace CliTube\Bridge\Spiral\Command;

use CliTube\Bridge\Spiral\Paginator\OffsetPaginator;
use CliTube\Component\Paginator;
use Spiral\Pagination\PaginableInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Pagination helper for console commands.
 * Should be used in a child of {@see \Spiral\Console\Command}.
 *
 * @property-read ?OutputInterface $output
 */
trait PaginationTrait
{
    public function paginate(iterable $data): void
    {
        $paginator = match(true) {
            $data instanceof \CliTube\Contract\Pagination\Paginator => $data,
            $data instanceof PaginableInterface && $data instanceof \IteratorAggregate && $data instanceof \Countable
                => OffsetPaginator::create($data),
            default => throw new \InvalidArgumentException('Can not paginate that data.'),
        };
        $core = (new \CliTube\Core($this->output));
        $core->createComponent(Paginator::class, [
            $paginator,
        ]);
        $core->run();
    }
}
