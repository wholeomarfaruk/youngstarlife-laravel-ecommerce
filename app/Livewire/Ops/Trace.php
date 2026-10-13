<?php

namespace App\Livewire\Ops;

use App\Services\Ops\Tracer;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Overview', 'title' => 'Trace anything'])]
#[Title('Trace')]
class Trace extends Component
{
    #[Url] public string $q = '';

    #[Computed] public function result(): array { return app(Tracer::class)->trace($this->q); }
    #[Computed] public function matches(): array { return app(Tracer::class)->resolve($this->q); }

    public function scan(string $code): void { $this->q = trim($code); unset($this->result, $this->matches); }

    public function render() { return view('livewire.ops.trace'); }
}
