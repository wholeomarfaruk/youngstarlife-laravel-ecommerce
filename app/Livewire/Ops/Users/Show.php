<?php

namespace App\Livewire\Ops\Users;

use App\Models\Ops\{ActivityLog, Batch, BlacklistEntry, Customer, Order, PurchaseOrder, Supplier, Transaction};
use App\Models\User;
use App\Services\Ops\{Blacklist, UserAccounts};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Computed, Layout, Url};
use Livewire\Component;

/** Master profile: the user account with its linked Customer and Supplier profiles, edited in one place. */
#[Layout('layouts.ops', ['module' => 'Users', 'title' => 'User', 'tabs' => 'parties'])]
class Show extends Component
{
    public User $user;
    #[Url(except: 'overview')] public string $tab = 'overview';   // overview | customer | supplier | activity
    public array $a = [];   // account
    public array $c = [];   // customer profile
    public array $s = [];   // supplier profile

    public function mount(User $user): void
    {
        $this->user = $user;
        if (! in_array($this->tab, ['overview', 'customer', 'supplier', 'activity'])) $this->tab = 'overview';
        $this->fillForms();
    }

    protected function fillForms(): void
    {
        $this->user->load('customer', 'supplier', 'warehouse:id,name');
        $str = fn (?array $a) => array_map(fn ($v) => is_bool($v) ? $v : (string) $v, $a ?? []);
        $this->a = $str($this->user->only('name', 'phone', 'email'));
        $this->c = $str($this->user->customer?->only('address', 'area', 'source', 'note'));
        $this->s = $str($this->user->supplier?->only('contact_name', 'address', 'categories', 'is_active'));
        unset($this->customerData, $this->supplierData, $this->activity, $this->blacklist);
    }

    #[Computed]
    public function customerData(): ?array
    {
        if (! $cu = $this->user->customer) return null;
        return ['orders' => Order::live()->where('customer_id', $cu->id)->latest('placed_at')->limit(10)->get(['id', 'number', 'placed_at', 'total', 'status'])];
    }

    /** purchased = good units received × cost · paid = PO payments + recorded supplier payments (same as the Suppliers page) */
    #[Computed]
    public function supplierData(): ?array
    {
        if (! $sup = $this->user->supplier) return null;
        $paid = Transaction::where('direction', 'out')->where(fn ($q) => $q->where(fn ($q) => $q->where('ref_type', (new PurchaseOrder)->getMorphClass())->whereIn('ref_id', $sup->purchaseOrders()->select('id')))
            ->orWhere(fn ($q) => $q->where('ref_type', $sup->getMorphClass())->where('ref_id', $sup->id)))->sum('amount');
        return [
            'pos' => $sup->purchaseOrders()->with('items:id,purchase_order_id,qty,unit_cost')->latest('ordered_at')->limit(10)->get(),
            'po_count' => $sup->purchaseOrders()->count(), 'paid' => (int) $paid,
            'purchased' => (int) Batch::where('supplier_id', $sup->id)->sum(DB::raw('(qty - damaged) * unit_cost')),
            'stock' => (int) Batch::where('supplier_id', $sup->id)->sum('remaining'),
            'open_returns' => $sup->returns()->whereNotIn('status', ['resolved', 'rejected', 'cancelled'])->count(),
        ];
    }

    /** Users → Blacklist entries for this person: their phone(s), their customer profile, and every device of the account */
    #[Computed]
    public function blacklist()
    {
        $phones = array_filter([$this->user->phone, $this->user->customer?->phone]);
        $devices = \App\Models\Device::where('user_id', $this->user->id)->pluck('id')->map(fn ($id) => (string) $id)->all();
        return BlacklistEntry::with('creator:id,name')->where(fn ($q) => $q->where(fn ($q) => $q->where('type', 'phone')->whereIn('value', $phones ?: ['-']))
            ->orWhere(fn ($q) => $q->where('type', 'device')->whereIn('value', $devices ?: ['-']))
            ->when($this->user->customer, fn ($q, $c) => $q->orWhere('customer_id', $c->id))
            ->when($this->user->email, fn ($q, $e) => $q->orWhere(fn ($q) => $q->where('type', 'email')->where('value', mb_strtolower($e)))))
            ->orderByDesc('is_active')->latest('id')->get();
    }

    /** everything logged against the account or either profile */
    #[Computed]
    public function activity()
    {
        $subjects = array_filter([[$this->user->getMorphClass(), $this->user->id], $this->user->customer ? [$this->user->customer->getMorphClass(), $this->user->customer->id] : null, $this->user->supplier ? [$this->user->supplier->getMorphClass(), $this->user->supplier->id] : null]);
        return ActivityLog::with('user:id,name')->where(fn ($q) => collect($subjects)->each(fn ($s) => $q->orWhere(fn ($q) => $q->where('subject_type', $s[0])->where('subject_id', $s[1]))))->latest('created_at')->limit(50)->get();
    }

    public function saveAccount(): void
    {
        $cid = $this->user->customer?->id;   // a customer profile needs a unique phone
        $this->validate([
            'a.name' => 'required|max:120',
            'a.email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'a.phone' => array_filter([$cid ? 'required' : 'nullable', 'max:20', Rule::unique('users', 'phone')->ignore($this->user->id), $cid ? Rule::unique('ops_customers', 'phone')->ignore($cid) : null]),
        ], ['a.phone.unique' => 'This phone is already used by another user or customer.']);
        $v = array_map(fn ($x) => $x === '' ? null : $x, $this->a);
        DB::transaction(function () use ($v) {
            $this->user->forceFill(['name' => $v['name'], 'phone' => $v['phone'], 'email' => $v['email']])->save();
            $this->user->customer?->update(['name' => $v['name'], 'phone' => $v['phone'], 'email' => $v['email']]);   // linked profiles follow the account
            $this->user->supplier?->update(['name' => $v['name'], 'phone' => $v['phone']]);
        });
        ops_log('Updated user '.$this->user->name, $this->user);
        $this->fillForms(); $this->toast('Account saved');
    }

    public function saveCustomer(): void
    {
        $this->validate(['c.address' => 'nullable|max:255', 'c.area' => 'nullable|max:120', 'c.source' => 'nullable|in:'.implode(',', array_keys(config('ops.sources'))), 'c.note' => 'nullable|max:2000']);
        $this->user->customer->update(array_map(fn ($x) => $x === '' ? null : $x, $this->c));
        ops_log('Updated customer profile of '.$this->user->name, $this->user->customer);
        $this->fillForms(); $this->toast('Customer profile saved');
    }

    public function saveSupplier(): void
    {
        $this->validate(['s.contact_name' => 'nullable|max:120', 's.address' => 'nullable|max:255', 's.categories' => 'nullable|max:255', 's.is_active' => 'bool']);
        $this->user->supplier->update(array_map(fn ($x) => $x === '' ? null : $x, $this->s));
        ops_log('Updated supplier profile of '.$this->user->name, $this->user->supplier); cache()->forget('suppliers.count');
        $this->fillForms(); $this->toast('Supplier profile saved');
    }

    public function addProfile(string $kind): void
    {
        try { app(UserAccounts::class)->attach($this->user, $kind); } catch (\InvalidArgumentException $e) { $this->toast($e->getMessage(), 'bad'); return; }
        ops_log('Added '.$kind.' profile to '.$this->user->name, $this->user);
        foreach (['users', 'customers', 'suppliers'] as $k) cache()->forget("$k.count");
        $this->fillForms(); $this->tab = $kind; $this->toast(ucfirst($kind).' profile added');
    }

    public function toggleBlock(): void
    {
        $cu = $this->user->customer; $bl = app(Blacklist::class);
        if ($cu->is_blocked) { $n = $bl->unblockCustomer($cu); $this->fillForms(); $this->toast("Customer unblocked · $n blacklist entr".($n === 1 ? 'y' : 'ies').' removed'); return; }
        try { $list = $bl->blockCustomer($cu, ['reason' => 'other', 'note' => 'Blocked from the user profile'], true); }
        catch (\InvalidArgumentException $e) { $this->toast($e->getMessage(), 'bad'); return; }
        $this->fillForms(); $this->toast('Customer blacklisted · phone'.(count($list) > 1 ? ' + '.(count($list) - 1).' device(s)' : ''));
    }

    /** lost phone and no recovery codes: an admin switches the person's two-factor off so they can sign in with the password */
    public function resetTwoFactor(): void
    {
        abort_unless(auth()->user()->role === 'admin' || auth()->user()->ops_role === 'Admin', 403);
        $this->user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        ops_log('Reset two-factor sign-in of '.$this->user->name, $this->user);
        $this->toast('Two-factor turned off for '.$this->user->name);
    }

    protected function toast(string $message, string $type = 'ok'): void { $this->dispatch('toast', message: $message, type: $type); }

    public function render() { return view('livewire.ops.users.show')->title($this->user->name); }
}
