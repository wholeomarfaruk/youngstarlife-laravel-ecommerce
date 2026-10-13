<form wire:submit="save" class="flex flex-col gap-3">
    <div class="-mt-1 flex gap-1 overflow-x-auto border-b border-line-2">@foreach (['homepage' => 'Homepage', 'coupons' => 'Coupons', 'announcement' => 'Announcement bar'] as $k => $l)<button type="button" wire:click="$set('tab','{{ $k }}')" @class(['mtab', 'mtab-on' => $tab === $k])>{{ $l }}</button>@endforeach</div>
    <x-admin.card>
        @if ($tab === 'homepage')<div class="grid gap-3"><x-admin.field label="Hero title"><input wire:model="data.hero_title" class="input"></x-admin.field><x-admin.field label="Hero subtitle"><input wire:model="data.hero_subtitle" class="input"></x-admin.field></div>
        @elseif ($tab === 'coupons')@foreach ($data['coupons'] ?? [] as $i => $c)<div class="mb-2 grid gap-2 sm:grid-cols-4"><input wire:model="data.coupons.{{ $i }}.code" class="input mono" placeholder="CODE"><select wire:model="data.coupons.{{ $i }}.type" class="input"><option value="percent">% off</option><option value="fixed">৳ off</option><option value="free_shipping">Free delivery</option><option value="bogo">Buy X get Y</option></select><input type="number" wire:model="data.coupons.{{ $i }}.value" class="input" placeholder="Value"><input type="number" wire:model="data.coupons.{{ $i }}.min" class="input" placeholder="Min order"></div>@endforeach<button type="button" wire:click="addCoupon" class="btn-secondary btn-sm">+ Add coupon</button>
        @else <x-admin.field label="Announcement text"><input wire:model="data.announcement" class="input"></x-admin.field> @endif
    </x-admin.card>
    <div class="flex justify-end"><button class="btn-primary">Publish</button></div>
</form>
