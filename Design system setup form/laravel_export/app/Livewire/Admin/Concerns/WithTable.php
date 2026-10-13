<?php

namespace App\Livewire\Admin\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

/** Search, status chip, date range, sort, per-page and bulk selection — all synced to the URL. */
trait WithTable
{
    use WithPagination;

    #[Url(except: '')] public string $search = '';
    #[Url(except: 'all')] public string $status = 'all';
    #[Url(except: '')] public string $from = '';
    #[Url(except: '')] public string $to = '';
    #[Url(except: [])] public array $filters = [];
    #[Url] public string $sortField = 'id';
    #[Url] public string $sortDir = 'desc';
    public int $perPage = 25;
    public array $selected = [];

    public function updating($name): void
    {
        if (in_array(explode('.', $name)[0], ['search', 'status', 'from', 'to', 'filters'])) { $this->resetPage(); $this->selected = []; }
    }

    public function sortBy(string $field): void
    {
        $this->sortDir = $this->sortField === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
    }

    public function clearFilters(): void { $this->reset('search', 'status', 'from', 'to', 'filters'); $this->resetPage(); }

    protected function dateRange($q, string $col)
    {
        return $q->when($this->from, fn ($q) => $q->whereDate($col, '>=', $this->from))->when($this->to, fn ($q) => $q->whereDate($col, '<=', $this->to));
    }

    protected function toast(string $message, string $type = 'ok'): void { $this->dispatch('toast', message: $message, type: $type); }
}
