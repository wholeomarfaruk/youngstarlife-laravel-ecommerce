<?php

namespace App\Livewire\Ops\Attributes;

use App\Models\Ops\{Attribute, AttributeValue, Product, ProductVariant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Computed, Layout, Title, Url};
use Livewire\Component;
use App\Livewire\Ops\Concerns\InventoryStats;

/** Global attributes (Color, Size, Material …) + their values — defined once, reused on every product. */
#[Layout('layouts.ops', ['module' => 'Product & Inventory', 'title' => 'Attributes', 'tabs' => 'inventory'])]
#[Title('Attributes')]
class Index extends Component
{
    use InventoryStats;
    #[Url(as: 'a')] public ?int $open = null;   // selected attribute
    public array $form = ['name' => '', 'type' => 'text', 'is_filterable' => true];
    public array $terms = [];                   // [[id, value, hex]] of the open attribute
    public string $newValues = '';              // "Red, Maroon, Pink" → bulk add
    public string $newHex = '#1F5F4A';

    public function mount(): void
    {
        $this->open ??= Attribute::orderBy('sort')->value('id');
        $this->load();
    }

    #[Computed]
    public function attributeList()
    {
        $used = Product::whereNotNull('options')->pluck('options')->flatten(1)->countBy('attribute_id');
        return Attribute::withCount('terms')->orderBy('sort')->orderBy('id')->get()->each(fn ($a) => $a->products = $used[$a->id] ?? 0);
    }

    /** value => number of variants using it (for the open attribute) */
    #[Computed]
    public function usage(): array
    {
        $a = $this->open ? Attribute::find($this->open) : null; if (! $a) return [];
        return ProductVariant::whereNotNull('options')->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(options, ?)) v, COUNT(*) n', ['$."'.$a->name.'"'])->groupBy('v')->pluck('n', 'v')->all();
    }

    public function select(?int $id): void { $this->open = $id; $this->resetValidation(); $this->newValues = ''; $this->load(); }

    protected function load(): void
    {
        $a = $this->open ? Attribute::with('terms')->find($this->open) : null;
        if (! $a) { $this->open = null; $this->form = ['name' => '', 'type' => 'text', 'is_filterable' => true]; $this->terms = []; return; }
        $this->form = ['name' => $a->name, 'type' => $a->type, 'is_filterable' => $a->is_filterable];
        $this->terms = $a->terms->map(fn ($t) => ['id' => $t->id, 'value' => $t->value, 'hex' => $t->hex])->all();
        unset($this->usage);
    }

    public function create(): void { $this->open = null; $this->load(); $this->resetValidation(); }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'max:40', Rule::unique('ops_attributes', 'name')->ignore($this->open)],
            'form.type' => 'required|in:'.implode(',', array_keys(Attribute::TYPES)),
            'terms.*.value' => 'required|max:60|distinct', 'terms.*.hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], ['terms.*.value.distinct' => 'Duplicate value', 'terms.*.value.required' => 'Value cannot be empty']);

        DB::transaction(function () {
            $a = $this->open ? Attribute::findOrFail($this->open) : new Attribute(['sort' => (int) Attribute::max('sort') + 1]);
            $oldName = $a->name;
            $a->fill(['name' => trim($this->form['name']), 'slug' => str($this->form['name'])->slug(), 'type' => $this->form['type'], 'is_filterable' => (bool) $this->form['is_filterable']])->save();
            if ($oldName && $oldName !== $a->name) $this->renameKey($oldName, $a->name);   // keep variants' options in sync
            foreach ($this->terms as $i => $t) {
                $row = AttributeValue::find($t['id']); $old = $row?->value; $val = trim($t['value']);
                AttributeValue::updateOrCreate(['id' => $t['id']], ['attribute_id' => $a->id, 'value' => $val, 'slug' => str($val)->slug(), 'hex' => $a->type === 'color' ? ($t['hex'] ?: null) : null, 'sort' => $i]);
                if ($old && $old !== $val) $this->renameValue($a, $old, $val);
            }
            $this->open = $a->id;
        });
        Attribute::flush(); static::flushInv(); ops_log('Saved attribute '.$this->form['name']);
        $this->load(); unset($this->attributeList);
        $this->dispatch('toast', message: 'Attribute saved');
    }

    /** "Red, Maroon, Pink" → new values (skips ones that already exist) */
    public function addValues(): void
    {
        if (! $this->open) { $this->save(); if (! $this->open) return; }
        $a = Attribute::findOrFail($this->open); $sort = (int) $a->terms()->max('sort'); $added = 0;
        foreach (array_unique(array_filter(array_map('trim', preg_split('/[,\n]/', $this->newValues)))) as $v) {
            if (mb_strlen($v) > 60 || $a->terms()->where('value', $v)->exists()) continue;
            $a->terms()->create(['value' => $v, 'slug' => str($v)->slug(), 'hex' => $a->type === 'color' ? $this->newHex : null, 'sort' => ++$sort]); $added++;
        }
        $this->newValues = ''; Attribute::flush(); $this->load(); unset($this->attributeList);
        $this->dispatch('toast', message: $added ? "$added values added" : 'Nothing new to add', type: $added ? 'ok' : 'bad');
    }

    public function move(int $i, int $dir): void
    {
        $j = $i + $dir; if (! isset($this->terms[$i], $this->terms[$j])) return;
        [$this->terms[$i], $this->terms[$j]] = [$this->terms[$j], $this->terms[$i]];
        foreach ($this->terms as $k => $t) if ($t['id']) AttributeValue::whereKey($t['id'])->update(['sort' => $k]);
        Attribute::flush();
    }

    /** a value used by any variant cannot be removed (it would orphan the variant's option) */
    public function removeValue(int $i): void
    {
        $t = $this->terms[$i] ?? null; if (! $t) return;
        if ($n = $this->usage[$t['value']] ?? 0) { $this->dispatch('toast', message: "Used by $n variants — rename it instead", type: 'bad'); return; }
        if ($t['id']) {
            AttributeValue::whereKey($t['id'])->delete();
            foreach (Product::whereJsonContains('options', ['attribute_id' => $this->open])->get() as $p)   // drop from products' picked values
                $p->update(['options' => collect($p->options)->map(fn ($x) => $x['attribute_id'] == $this->open ? ['attribute_id' => $x['attribute_id'], 'values' => array_values(array_diff($x['values'], [$t['id']]))] : $x)->all()]);
        }
        unset($this->terms[$i]); $this->terms = array_values($this->terms); Attribute::flush();
        $this->dispatch('toast', message: 'Value removed');
    }

    public function delete(): void
    {
        $a = Attribute::findOrFail($this->open);
        if ($n = Product::whereJsonContains('options', ['attribute_id' => $a->id])->count()) { $this->dispatch('toast', message: "Used by $n products — remove it from them first", type: 'bad'); return; }
        $a->delete(); Attribute::flush(); static::flushInv(); ops_log('Deleted attribute '.$a->name);
        $this->open = Attribute::orderBy('sort')->value('id'); $this->load(); unset($this->attributeList);
        $this->dispatch('toast', message: 'Attribute deleted');
    }

    public function moveAttribute(int $id, int $dir): void
    {
        $list = Attribute::orderBy('sort')->orderBy('id')->pluck('id')->all(); $i = array_search($id, $list); $j = $i + $dir;
        if ($i === false || ! isset($list[$j])) return;
        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        foreach ($list as $k => $aid) Attribute::whereKey($aid)->update(['sort' => $k + 1]);
        Attribute::flush(); unset($this->attributeList);
    }

    // ── keep ops_product_variants.options (+ the colour / size copies) in sync when names change
    protected function renameKey(string $old, string $new): void
    {
        ProductVariant::whereNotNull('options')->whereRaw('JSON_CONTAINS_PATH(options, "one", ?)', ['$."'.$old.'"'])->each(function ($v) use ($old, $new) {
            $o = $v->options; $o[$new] = $o[$old]; unset($o[$old]); $v->update(['options' => $o]);
        });
    }
    protected function renameValue(Attribute $a, string $old, string $new): void
    {
        $col = ProductVariant::columnFor($a);
        ProductVariant::whereRaw('JSON_UNQUOTE(JSON_EXTRACT(options, ?)) = ?', ['$."'.$a->name.'"', $old])->each(function ($v) use ($a, $new, $col) {
            $o = $v->options; $o[$a->name] = $new; $v->update(['options' => $o] + ($col ? [$col => $new] : []));
        });
    }

    public function render() { return view('livewire.ops.attributes.index'); }
}
