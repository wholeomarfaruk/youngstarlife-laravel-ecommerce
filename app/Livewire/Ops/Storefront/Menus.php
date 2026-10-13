<?php

namespace App\Livewire\Ops\Storefront;

use App\Models\Ops\{Category, Product};
use App\Models\Storefront\{Menu, MenuItem};
use App\Services\Storefront\MenuService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Computed, Locked, Url};
use Livewire\Component;

/**
 * Menu builder (Ops → Storefront → Navigation menus) for every location in the registry (config/storefront.php → menus).
 * Drag & drop ordering / nesting (SortableJS), searchable category + product pickers (Tom Select).
 */
class Menus extends Component
{
    #[Url(as: 'menu')] public string $location = 'header';
    public ?int $editingId = null;   // null + $editing = new item
    public bool $editing = false;
    public array $f = [];
    public string $title = '';
    #[Locked] public string $autoLabel = '';   // last suggested label; replaced on pick until the admin types their own

    protected const BLANK = ['type' => 'route', 'label' => '', 'value' => 'home', 'ref_id' => null, 'icon' => '', 'new_tab' => false, 'is_active' => true, 'parent_id' => null];

    public function mount(): void
    {
        MenuService::ensureRegistered();
        if (! array_key_exists($this->location, MenuService::locations())) $this->location = array_key_first(MenuService::locations());
        $this->f = self::BLANK;
        $this->title = (string) $this->menu->title;
    }

    public function updatedLocation(): void
    {
        if (! array_key_exists($this->location, MenuService::locations())) $this->location = array_key_first(MenuService::locations());
        $this->cancel(); unset($this->menu, $this->tree);
        $this->title = (string) $this->menu->title;
    }

    #[Computed] public function menu(): Menu { return Menu::where('location', $this->location)->firstOrFail(); }
    #[Computed] public function depth(): int { return (int) (MenuService::locations()[$this->location]['depth'] ?? 1); }
    #[Computed] public function categories() { return Category::orderBy('name')->pluck('name', 'id'); }

    /** all items (inactive too) as a 2-level tree, each with the URL it resolves to (null = broken link) */
    #[Computed]
    public function tree(): array
    {
        $svc = app(MenuService::class);
        $items = MenuItem::where('menu_id', $this->menu->id)->orderBy('sort_order')->get();
        $node = fn (MenuItem $i) => ['id' => $i->id, 'label' => $i->label, 'type' => $i->type, 'icon' => $i->icon, 'new_tab' => $i->new_tab, 'is_active' => $i->is_active, 'url' => $svc->url($i)];
        return $items->whereNull('parent_id')->map(fn ($i) => $node($i) + ['children' => $items->where('parent_id', $i->id)->map($node)->values()->all()])->values()->all();
    }

    /** product picker (searchable select) */
    #[Computed] public function products() { return Product::orderBy('name')->get(['id', 'name', 'status', 'featured_image']); }

    public function edit(?int $id = null, ?int $parent = null): void
    {
        $this->resetValidation();
        $this->editingId = $id; $this->editing = true;
        $this->f = $id
            ? array_merge(self::BLANK, MenuItem::where('menu_id', $this->menu->id)->findOrFail($id)->only(array_keys(self::BLANK)))
            : array_merge(self::BLANK, ['parent_id' => $this->depth > 1 ? $parent : null]);
        $this->f['icon'] = (string) $this->f['icon'];
        $this->autoLabel = $id ? '' : $this->suggestLabel();
        if (! $id) $this->f['label'] = $this->autoLabel;
    }

    public function cancel(): void { $this->editing = false; $this->editingId = null; $this->f = self::BLANK; $this->resetValidation(); }

    /** picking a type / page / category / product fills the label until the admin types their own */
    public function updated(string $prop): void
    {
        if ($prop === 'f.type') {
            $this->f['value'] = match ($this->f['type']) { 'route' => 'home', 'whatsapp' => '8801', 'phone' => '+8801', default => '' };
            $this->f['ref_id'] = null;
            if ($this->f['type'] === 'whatsapp' && $this->f['icon'] === '') $this->f['icon'] = 'fa-brands fa-whatsapp';
            if ($this->f['type'] === 'phone' && $this->f['icon'] === '') $this->f['icon'] = 'fa-solid fa-phone';
        }
        if (in_array($prop, ['f.type', 'f.value', 'f.ref_id'])) {
            $suggest = $this->suggestLabel();
            if ($this->f['label'] === '' || $this->f['label'] === $this->autoLabel) $this->f['label'] = $suggest;
            $this->autoLabel = $suggest;
        }
    }

    protected function suggestLabel(): string
    {
        return (string) match ($this->f['type']) {
            'route' => config('storefront.menu_routes.'.$this->f['value'].'.0', ''),
            'category' => $this->f['ref_id'] ? Category::whereKey($this->f['ref_id'])->value('name') : '',
            'product' => $this->f['ref_id'] ? Product::whereKey($this->f['ref_id'])->value('name') : '',
            'whatsapp' => 'WhatsApp',
            default => '',
        };
    }

    public function save(): void
    {
        $topLevel = MenuItem::where('menu_id', $this->menu->id)->whereNull('parent_id')->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->pluck('id')->all();
        $hasChildren = $this->editingId && MenuItem::where('parent_id', $this->editingId)->exists();
        $this->validate([
            'f.type' => ['required', Rule::in(array_keys(config('storefront.menu_item_types')))],
            'f.label' => 'required|string|max:120',
            'f.value' => match ($this->f['type']) {
                'route' => ['required', Rule::in(array_keys(config('storefront.menu_routes')))],
                'url' => ['required', 'max:500', 'regex:#^(https?://|/|mailto:|tel:)#i'],
                'whatsapp' => ['required', 'regex:/^\+?\d[\d\s-]{7,18}$/'],
                'phone' => ['required', 'regex:/^\+?\d[\d\s-]{5,18}$/'],
                default => ['nullable'],
            },
            'f.ref_id' => match ($this->f['type']) {
                'category' => ['required', 'integer', Rule::exists('ops_categories', 'id')],
                'product' => ['required', 'integer', Rule::exists('ops_products', 'id')],
                default => ['nullable'],
            },
            'f.icon' => ['nullable', 'max:80', 'regex:/^[a-z0-9 -]*$/'],
            'f.parent_id' => ['nullable', 'integer', Rule::in($this->depth > 1 && ! $hasChildren ? $topLevel : [])],
        ], [
            'f.value.regex' => match ($this->f['type']) { 'url' => 'Link must start with / (this site) or https://', default => 'Enter a valid number, e.g. 8801XXXXXXXXX' },
            'f.ref_id.required' => 'Pick a '.$this->f['type'].'.', 'f.parent_id.in' => 'This item can\'t go under that one.', 'f.icon.regex' => 'Icon is a Font Awesome class, e.g. fa-solid fa-star',
        ]);

        $data = array_intersect_key($this->f, self::BLANK);
        $data['value'] = in_array($data['type'], ['category', 'product']) ? null : trim((string) $data['value']);
        $data['ref_id'] = in_array($data['type'], ['category', 'product']) ? (int) $data['ref_id'] : null;
        $data['icon'] = trim((string) $data['icon']) ?: null;
        $data['parent_id'] = $data['parent_id'] ?: null;

        if ($this->editingId) {
            $item = MenuItem::where('menu_id', $this->menu->id)->findOrFail($this->editingId);
            if ((int) $item->parent_id !== (int) $data['parent_id']) $data['sort_order'] = $this->nextOrder($data['parent_id']);
            $item->update($data);
        } else {
            $item = MenuItem::create($data + ['menu_id' => $this->menu->id, 'sort_order' => $this->nextOrder($data['parent_id'])]);
        }
        ops_log(($this->editingId ? 'Updated' : 'Added').' menu item "'.$item->label.'" · '.$this->menu->name);
        $this->dispatch('toast', message: $this->editingId ? 'Menu item saved' : 'Menu item added');
        $this->cancel(); unset($this->tree);
    }

    protected function nextOrder(?int $parent): int
    {
        return (int) MenuItem::where('menu_id', $this->menu->id)->where('parent_id', $parent)->max('sort_order') + 1;
    }

    public function toggle(int $id): void
    {
        $item = MenuItem::where('menu_id', $this->menu->id)->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        ops_log(($item->is_active ? 'Showed' : 'Hid').' menu item "'.$item->label.'"');
        unset($this->tree);
    }

    public function delete(int $id): void
    {
        $item = MenuItem::where('menu_id', $this->menu->id)->findOrFail($id);
        $item->delete();   // its dropdown items go with it (FK cascade)
        MenuService::forget($this->location);
        ops_log('Removed menu item "'.$item->label.'" · '.$this->menu->name);
        if ($this->editingId === $id) $this->cancel();
        $this->dispatch('toast', message: 'Menu item removed'); unset($this->tree);
    }

    /** drag & drop result from sortableTree: [{id, children: [{id}]}] — only this menu's items, never deeper than the location allows */
    public function reorder(array $tree): void
    {
        $own = MenuItem::where('menu_id', $this->menu->id)->pluck('id')->all();
        DB::transaction(function () use ($tree, $own) {
            foreach (array_values($tree) as $i => $node) {
                if (! in_array((int) ($node['id'] ?? 0), $own)) continue;
                MenuItem::whereKey($node['id'])->update(['parent_id' => null, 'sort_order' => $i]);
                foreach (array_values($node['children'] ?? []) as $j => $child) {
                    if (! in_array((int) ($child['id'] ?? 0), $own)) continue;
                    // a location without dropdowns flattens anything dropped inside another item
                    MenuItem::whereKey($child['id'])->update($this->depth > 1 ? ['parent_id' => $node['id'], 'sort_order' => $j] : ['parent_id' => null, 'sort_order' => count($tree) + $j]);
                }
            }
        });
        MenuService::forget($this->location);   // bulk updates skip model events
        unset($this->tree);
        $this->dispatch('toast', message: 'Menu order saved');
    }

    public function saveTitle(): void
    {
        $this->validate(['title' => 'nullable|max:255']);
        $this->menu->update(['title' => trim($this->title) ?: null]);
        ops_log('Updated '.$this->menu->name.' heading');
        $this->dispatch('toast', message: 'Heading saved');
    }

    public function render() { return view('livewire.ops.storefront.menus'); }
}
