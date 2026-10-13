<?php

namespace App\Livewire\Ops\Users;

use App\Models\Ops\{Customer, Supplier};
use App\Models\User;
use App\Livewire\Ops\Concerns\WithTable;
use App\Services\Ops\UserAccounts;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;

/** Every customer / supplier starts as a user account; the Customer / Supplier profile is added on top and stays linked (user_id). */
#[Layout('layouts.ops', ['module' => 'Users', 'title' => 'Users', 'tabs' => 'parties'])]
#[Title('Users')]
class Index extends Component
{
    use WithTable;
    #[Url(except: 'new')] public string $sort = 'new';   // new | name
    public array $u = [];
    protected bool $autoOpen = false;

    protected const BLANK = ['name' => '', 'phone' => '', 'email' => '', 'profile' => 'customer', 'address' => '', 'area' => '', 'source' => '', 'contact_name' => '', 'categories' => ''];

    /** ?new=customer|supplier (from the Customers / Suppliers pages) opens the add form with that profile picked */
    public function mount(): void
    {
        $this->u = self::BLANK;
        if (in_array($p = request('new'), ['customer', 'supplier'])) { $this->edit($p); $this->autoOpen = true; }
    }

    protected function base()
    {
        return User::query()->with('customer:id,user_id,orders_count,total_spent,is_blocked', 'supplier:id,user_id,due,is_active')
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")));
    }

    protected function applyStatus($q, string $s)
    {
        return match ($s) {
            'customers' => $q->has('customer'), 'suppliers' => $q->has('supplier'), 'staff' => $q->staff(), 'blocked' => $q->whereHas('customer', fn ($c) => $c->where('is_blocked', true)),
            'none' => $q->doesntHave('customer')->doesntHave('supplier')->where('role', '!=', 'admin')->whereNull('ops_role'), default => $q,
        };
    }

    #[Computed] public function counts(): array { return collect(['all', 'customers', 'suppliers', 'staff', 'blocked', 'none'])->mapWithKeys(fn ($s) => [$s => $this->applyStatus($this->base(), $s)->count()])->all(); }

    #[Computed]
    public function rows()
    {
        $q = $this->applyStatus($this->base(), $this->status);
        return ($this->sort === 'name' ? $q->orderBy('name') : $q->latest('id'))->paginate($this->perPage);
    }

    #[Computed]
    public function kpis(): array
    {
        return ['users' => User::count(), 'new' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'customers' => Customer::whereNotNull('user_id')->count(), 'suppliers' => Supplier::whereNotNull('user_id')->count(), 'staff' => User::staff()->count()];
    }

    /** Resets the add-user form (opened by Alpine) with the profile to create. Editing happens on the user's master profile. */
    public function edit(string $profile = 'customer'): void
    {
        $this->resetValidation();
        $this->u = array_merge(self::BLANK, ['profile' => in_array($profile, ['customer', 'supplier']) ? $profile : 'customer']);
    }

    /** Account first, then the profile linked to it → opens the new user's master profile. */
    public function save()
    {
        $customer = $this->u['profile'] === 'customer';
        $this->validate([
            'u.name' => 'required|max:120',
            'u.email' => 'nullable|email|max:255|unique:users,email',
            'u.phone' => array_filter([$customer ? 'required' : 'nullable', 'max:20', 'unique:users,phone', $customer ? 'unique:ops_customers,phone' : null]),
            'u.profile' => 'required|in:customer,supplier',
            'u.address' => 'nullable|max:255', 'u.area' => 'nullable|max:120', 'u.source' => 'nullable|in:'.implode(',', array_keys(config('ops.sources'))),
            'u.contact_name' => 'nullable|max:120', 'u.categories' => 'nullable|max:255',
        ], ['u.phone.unique' => 'This phone is already in use — add the profile from that user’s row.']);
        $v = array_map(fn ($x) => $x === '' ? null : $x, $this->u);

        $user = DB::transaction(function () use ($v) {
            $user = app(UserAccounts::class)->open($v['profile'], $v['name'], $v['phone'], $v['email']);
            $v['profile'] === 'customer'
                ? Customer::create(['user_id' => $user->id, 'name' => $v['name'], 'phone' => $v['phone'], 'email' => $v['email'], 'address' => $v['address'], 'area' => $v['area'], 'source' => $v['source']])
                : Supplier::create(['user_id' => $user->id, 'name' => $v['name'], 'phone' => $v['phone'], 'contact_name' => $v['contact_name'], 'address' => $v['address'], 'categories' => $v['categories']]);
            return $user;
        });
        ops_log('Added user '.$user->name.' with '.$v['profile'].' profile', $user); $this->forgetCounts();
        session()->flash('toast', 'User and '.$v['profile'].' profile added');
        return $this->redirectRoute('ops.users.show', ['user' => $user, 'tab' => $v['profile']], navigate: true);
    }

    public function addProfile(int $id, string $kind): void
    {
        $user = User::with('customer', 'supplier')->findOrFail($id);
        try { app(UserAccounts::class)->attach($user, $kind); } catch (\InvalidArgumentException $e) { $this->toast($e->getMessage(), 'bad'); return; }
        ops_log('Added '.$kind.' profile to '.$user->name, $user); $this->forgetCounts();
        $this->toast(ucfirst($kind).' profile added to '.$user->name);
    }

    protected function forgetCounts(): void { foreach (['users', 'customers', 'suppliers'] as $k) cache()->forget("$k.count"); unset($this->rows, $this->counts, $this->kpis); }

    public function render() { return view('livewire.ops.users.index', ['autoOpen' => $this->autoOpen]); }
}
