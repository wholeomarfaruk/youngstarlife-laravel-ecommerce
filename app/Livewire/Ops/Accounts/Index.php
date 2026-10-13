<?php

namespace App\Livewire\Ops\Accounts;

use App\Models\Ops\{Account, Customer, Order, Shipment, Supplier, Transaction};
use App\Livewire\Ops\Concerns\WithTable;
use Livewire\Attributes\{Computed, Layout, Title};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Accounts & Finance', 'title' => 'Accounts & Finance'])]
#[Title('Accounts & Finance')]
class Index extends Component
{
    use WithTable;
    public bool $adding = false;
    public array $tx = ['direction' => 'out', 'account_id' => null, 'category' => 'Packaging', 'amount' => null, 'description' => ''];

    #[Computed] public function accounts() { return Account::orderBy('id')->get(); }
    /** money held by Steadfast / Pathao… (COD collected − charges − payments) — App\Services\Ops\CourierLedger */
    #[Computed] public function couriers() { return app(\App\Services\Ops\CourierLedger::class)->balances(); }

    #[Computed]
    public function rows()
    {
        $q = Transaction::with('account:id,name')->when($this->status !== 'all', fn ($q) => $q->where('direction', $this->status))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('description', 'like', "%$s%")->orWhere('category', 'like', "%$s%")))
            ->when($this->filters['account'] ?? null, fn ($q, $a) => $q->where('account_id', $a))->when($this->filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c));
        return $this->dateRange($q, 'date')->latest('date')->latest('id')->paginate($this->perPage);
    }

    #[Computed]
    public function cards(): array
    {
        $courier = (int) $this->couriers->sum('balance');
        $customers = (int) Customer::sum('due');
        $month = Order::live()->where('status', 'delivered')->whereBetween('placed_at', [now()->startOfMonth(), now()]);
        $gross = (int) (clone $month)->selectRaw('COALESCE(SUM(CAST(total AS SIGNED) - CAST(cost AS SIGNED) - CAST(delivery_charge AS SIGNED)),0) g')->value('g');
        $expenses = (int) Transaction::where('direction', 'out')->whereIn('category', ['Packaging', 'Marketing', 'Salary', 'Other'])->whereBetween('date', [now()->startOfMonth()->toDateString(), today()->toDateString()])->sum('amount');
        return ['courier' => $courier, 'customers' => $customers, 'suppliers' => (int) Supplier::where('due', '>', 0)->sum('due'), 'gross' => $gross, 'expenses' => $expenses, 'profit' => $gross - $expenses,
            'revenue' => (int) (clone $month)->sum('total')];
    }

    public function add(): void
    {
        $this->validate(['tx.amount' => 'required|integer|min:1', 'tx.account_id' => 'required|exists:ops_accounts,id', 'tx.description' => 'required|max:255', 'tx.direction' => 'required|in:in,out', 'tx.category' => 'required|in:'.implode(',', config('ops.transaction_categories'))],
            ['tx.account_id.required' => 'Choose an account.', 'tx.description.required' => 'Add a description.']);
        $t = Transaction::create($this->tx + ['date' => today(), 'user_id' => auth()->id()]);
        ops_log(($t->direction === 'in' ? 'Money in ' : 'Money out ').tk($t->amount).' · '.$t->description, $t);
        $this->reset('tx'); $this->adding = false; $this->toast('Transaction saved'); unset($this->accounts, $this->cards, $this->couriers);
    }

    public function export()
    {
        $rows = Transaction::with('account:id,name')->when($this->status !== 'all', fn ($q) => $q->where('direction', $this->status))->latest('date')->get();
        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w'); fputcsv($f, ['Date', 'Description', 'Category', 'Account', 'In', 'Out']);
            foreach ($rows as $t) fputcsv($f, [$t->date->toDateString(), $t->description, $t->category, $t->account->name, $t->direction === 'in' ? $t->amount : '', $t->direction === 'out' ? $t->amount : '']);
            fclose($f);
        }, 'transactions-'.now()->format('Ymd-His').'.csv');
    }

    public function render() { return view('livewire.ops.accounts.index'); }
}
