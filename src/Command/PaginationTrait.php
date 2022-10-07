<?php

declare(strict_types=1);

namespace CliTube\Bridge\Spiral\Command;

use CliTube\Bridge\Spiral\Paginator\OffsetPaginator;
use CliTube\Component\Paginator;
use Spiral\Pagination\PaginableInterface;

/**
 * @mixin \Spiral\Console\Command
 */
trait PaginationTrait
{
    public function paginate(iterable $data): void
    {
        $paginator = match(true) {
            $data instanceof \CliTube\Contract\Pagination\Paginator => $data,
            $data instanceof PaginableInterface && $data instanceof \IteratorAggregate && $data instanceof \Countable
                => OffsetPaginator::create($data),
            default => throw new InvalidArgumentException('Can not paginate that data.'),
        };
        $core = (new \CliTube\Core($this->output));
        $core->createComponent(Paginator::class, [
            $paginator,
        ]);
        $core->run();
    }
}
