<?php

namespace App\Livewire\Admin\Accounts;

use App\Models\{Account, Transaction};
use App\Livewire\Admin\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.admin', ['module' => 'Accounts', 'title' => 'Accounts'])]
#[Title('Accounts')]
class Index extends Component
{
    use WithTable;
    public array $tx = ['direction' => 'out', 'account_id' => null, 'category' => 'Packaging', 'amount' => null, 'description' => ''];

    #[Computed] public function accounts() { return Account::orderBy('id')->get(); }
    #[Computed] public function rows()
    {
        $q = Transaction::with('account:id,name')->when($this->status !== 'all', fn ($q) => $q->where('direction', $this->status))->when($this->search, fn ($q, $s) => $q->where('description', 'like', "%$s%"));
        return $this->dateRange($q, 'date')->latest('date')->latest('id')->paginate($this->perPage);
    }
    public function add(): void
    {
        $this->validate(['tx.amount' => 'required|integer|min:1', 'tx.account_id' => 'required|exists:accounts,id', 'tx.description' => 'required']);
        Transaction::create($this->tx + ['date' => today(), 'user_id' => auth()->id()]);
        $this->reset('tx'); $this->dispatch('close-modal'); $this->toast('Transaction saved'); unset($this->accounts);
    }
    public function render() { return view('livewire.admin.accounts.index'); }
}
