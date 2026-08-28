<?php

namespace App\Concerns;

trait InteractsWithInfiniteCards
{
    public int $perPage = 12;

    public function loadMore(): void
    {
        $this->perPage += 12;
    }

    public function updatedSearch(): void
    {
        $this->perPage = 12;
    }
}
