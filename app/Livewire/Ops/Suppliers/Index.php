<?php

namespace App\Livewire\Ops\Suppliers;

use App\Models\Ops\{Account, Batch, CustomerReturnItem, PurchaseOrder, Supplier, SupplierReturn, Transaction};
use App\Livewire\Ops\Concerns\WithTable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

#[Layout('layouts.ops', ['module' => 'Users', 'title' => 'Suppliers', 'tabs' => 'parties'])]
#[Title('Suppliers')]
class Index extends Component
{
    use WithTable;
    #[Url(except: 'due')] public string $sort = 'due';   // due | purchased | name | defect
    public ?int $editingId = null; public ?int $expanded = null;
    public array $s = ['name' => '', 'contact_name' => '', 'phone' => '', 'address' => '', 'categories' => '', 'is_active' => true];
    public ?int $payFor = null; public $payAmount = null; public string $payAccount = 'Cash'; public string $payNote = '';

    /** purchased = good units received × cost · paid = PO payments + recorded supplier payments */
    protected function base()
    {
        $poMorph = (new PurchaseOrder)->getMorphClass(); $supMorph = (new Supplier)->getMorphClass();
        return Supplier::query()->withCount(['purchaseOrders', 'batches', 'returns as open_returns' => fn ($q) => $q->whereNotIn('status', ['resolved', 'rejected', 'cancelled'])])
            ->withSum('batches as stock', 'remaining')
            ->selectSub('select coalesce(sum((qty - damaged) * unit_cost), 0) from ops_batches where supplier_id = ops_suppliers.id', 'purchased')
            ->selectSub('select coalesce(sum(qty), 0) from ops_batches where supplier_id = ops_suppliers.id', 'received_units')
            ->selectSub('select coalesce(sum(damaged), 0) from ops_batches where supplier_id = ops_suppliers.id', 'damaged_units')
            ->selectSub("select coalesce(sum(amount), 0) from ops_transactions t where t.direction = 'out' and ((t.ref_type = '".addslashes($poMorph)."' and t.ref_id in (select id from ops_purchase_orders where supplier_id = ops_suppliers.id)) or (t.ref_type = '".addslashes($supMorph)."' and t.ref_id = ops_suppliers.id))", 'paid')
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('contact_name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
            ->when($this->filters['category'] ?? null, fn ($q, $c) => $q->where('categories', 'like', "%$c%"));
    }

    protected function applyStatus($q, string $s)
    {
        return match ($s) { 'due' => $q->where('due', '>', 0), 'returns' => $q->whereHas('returns', fn ($r) => $r->whereNotIn('status', ['resolved', 'rejected', 'cancelled'])), 'active' => $q->where('is_active', true), 'inactive' => $q->where('is_active', false), default => $q };
    }

    #[Computed] public function counts(): array { return collect(['all', 'due', 'returns', 'active', 'inactive'])->mapWithKeys(fn ($s) => [$s => $this->applyStatus(Supplier::query(), $s)->count()])->all(); }

    #[Computed]
    public function rows()
    {
        $q = $this->applyStatus($this->base(), $this->status);
        $q = match ($this->sort) { 'purchased' => $q->orderByDesc('purchased'), 'name' => $q->orderBy('name'), 'defect' => $q->orderByRaw('damaged_units / GREATEST(received_units, 1) desc'), default => $q->orderByDesc('due') };
        return $q->paginate($this->perPage);
    }

    #[Computed]
    public function kpis(): array
    {
        $all = $this->base()->get();
        $recv = $all->sum('received_units');
        return ['suppliers' => $all->count(), 'purchased' => (int) $all->sum('purchased'), 'paid' => (int) $all->sum('paid'), 'due' => (int) $all->sum('due'), 'stock' => (int) $all->sum('stock'),
            'returns' => SupplierReturn::whereNotIn('status', ['resolved', 'rejected', 'cancelled'])->count(), 'credit' => (int) $all->sum('credit'), 'defect' => $recv ? round($all->sum('damaged_units') / $recv * 100, 1) : 0];
    }

    #[Computed] public function categories() { return Supplier::pluck('categories')->flatMap(fn ($c) => array_map('trim', explode(',', (string) $c)))->filter()->unique()->sort()->values(); }

    #[Computed]
    public function detail(): ?array
    {
        if (! $this->expanded) return null;
        $sup = Supplier::with('user:id,name,phone,email')->findOrFail($this->expanded);
        $batches = Batch::where('supplier_id', $sup->id)->with('variant.product:id,name', 'purchaseOrder:id,number')->latest('received_at')->limit(12)->get();
        $returns = SupplierReturn::where('supplier_id', $sup->id)->with('items')->latest()->limit(8)->get();
        // ledger: + received goods (per GRN) · − payments · − credits; running balance
        $entries = collect();
        foreach (Batch::where('supplier_id', $sup->id)->whereNotNull('purchase_order_id')->with('receipt:id,number', 'purchaseOrder:id,number')->get()->groupBy('goods_receipt_id') as $grp) {
            $b = $grp->first(); $entries->push(['date' => $b->received_at, 'text' => ($b->receipt?->number ?? 'Receipt').' · '.$b->purchaseOrder?->number, 'amount' => (int) $grp->sum(fn ($x) => ($x->qty - $x->damaged) * $x->unit_cost)]);
        }
        $poMorph = (new PurchaseOrder)->getMorphClass(); $supMorph = $sup->getMorphClass();
        foreach (Transaction::where('direction', 'out')->where(fn ($q) => $q->where(fn ($q) => $q->where('ref_type', $poMorph)->whereIn('ref_id', $sup->purchaseOrders()->select('id')))->orWhere(fn ($q) => $q->where('ref_type', $supMorph)->where('ref_id', $sup->id)))->with('account:id,name')->get() as $t)
            $entries->push(['date' => $t->date, 'text' => 'Payment · '.$t->account->name.' · '.$t->description, 'amount' => -$t->amount]);
        foreach ($returns->where('status', 'resolved')->where('resolution', 'credit') as $r) $entries->push(['date' => $r->resolved_at ?? $r->updated_at, 'text' => 'Credit · '.$r->code, 'amount' => -$r->value]);
        $bal = 0; $ledger = $entries->sortBy('date')->values()->map(function ($e) use (&$bal) { $bal += $e['amount']; return $e + ['balance' => $bal]; });
        return compact('sup', 'batches', 'returns', 'ledger');
    }

    public function toggle(int $id): void { $this->expanded = $this->expanded === $id ? null : $id; unset($this->detail); }

    /** Fills the supplier modal (opened by Alpine): empty for a new supplier, current values for an existing one. */
    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $this->editingId = $id;
        $this->s = $id ? Supplier::findOrFail($id)->only(array_keys($this->s)) : ['name' => '', 'contact_name' => '', 'phone' => '', 'address' => '', 'categories' => '', 'is_active' => true];
    }

    public function saveSupplier(): void
    {
        $this->validate(['s.name' => 'required|max:120', 's.phone' => 'nullable|max:20', 's.contact_name' => 'nullable|max:120', 's.address' => 'nullable|max:255', 's.categories' => 'nullable|max:255']);
        $sup = Supplier::updateOrCreate(['id' => $this->editingId], array_map(fn ($v) => $v === '' ? null : $v, $this->s) + ['name' => $this->s['name']]);
        ops_log(($this->editingId ? 'Updated' : 'Added').' supplier '.$sup->name, $sup); cache()->forget('suppliers.count');
        $this->dispatch('close-modal');
        $this->toast($this->editingId ? 'Supplier updated' : 'Supplier added');
        $this->editingId = null;
    }

    public function openPay(int $id): void { $this->payFor = $id; $this->payAmount = Supplier::find($id)?->due ?: null; $this->payNote = ''; $this->resetValidation(); $this->dispatch('open-modal', name: 'supplier-pay'); }

    /** Record payment → money out of an account, supplier due down */
    public function pay(): void
    {
        $this->validate(['payAmount' => 'required|integer|min:1', 'payAccount' => 'required|in:'.implode(',', config('ops.accounts'))]);
        $sup = Supplier::findOrFail($this->payFor);
        DB::transaction(function () use ($sup) {
            Transaction::create(['account_id' => Account::where('name', $this->payAccount)->value('id'), 'direction' => 'out', 'category' => 'Supplier', 'amount' => (int) $this->payAmount, 'description' => 'Supplier payment · '.$sup->name.($this->payNote ? ' · '.$this->payNote : ''), 'date' => today(), 'ref_type' => $sup->getMorphClass(), 'ref_id' => $sup->id, 'user_id' => auth()->id()]);
            $sup->decrement('due', (int) $this->payAmount);
        });
        ops_log('Paid '.$sup->name.' '.tk($this->payAmount).' from '.$this->payAccount, $sup);
        $this->dispatch('close-modal'); $this->toast('Payment recorded · '.tk($this->payAmount)); unset($this->detail, $this->rows, $this->kpis);
    }

    public function render() { return view('livewire.ops.suppliers.index'); }
}
