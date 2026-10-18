@php($nav = \App\Livewire\Ops\Storefront\Index::NAV)
<div class="grid items-start gap-3 lg:grid-cols-[230px_minmax(0,1fr)]" x-data="{ dirty: false }" x-on:saved.window="dirty = false" x-init="window.opsDirty = false; $watch('dirty', v => window.opsDirty = v)">
    <nav class="card flex max-h-[260px] flex-col gap-0.5 overflow-y-auto p-1.5 lg:sticky lg:top-[72px] lg:max-h-[calc(100vh-90px)]">
        @foreach ($nav as $group => $items)
            <span class="px-2.5 pb-0.5 pt-2 text-2xs font-semibold uppercase tracking-wide text-ink-muted">{{ $group }}</span>
            @foreach ($items as $k => $l)<button type="button" @click="if (!dirty || confirm('Discard unsaved changes?')) { dirty = false; $wire.set('tab', '{{ $k }}') }" @class(['rounded px-2.5 py-1.5 text-left', 'bg-soft font-semibold' => $tab === $k, 'text-ink-3 hover:bg-zebra' => $tab !== $k])>{{ $l }}</button>@endforeach
        @endforeach
    </nav>

    {{-- menu builder saves live (own component), so it sits outside the Publish form --}}
    @if ($tab === 'menus')
        <livewire:ops.storefront.menus />
    @elseif ($tab === 'products')
        <livewire:ops.storefront.product-order />   {{-- drag & drop / typed position, saves live --}}
    @else
    <form wire:submit="save" class="card" @input="dirty = true" @change="dirty = true">
        <div class="card-head"><span class="card-title">{{ $this->tabLabel() }}</span>@if ($tab !== 'overview')<a href="{{ url('/') }}" target="_blank" class="text-sm">View store ↗</a>@endif</div>
        <div class="flex flex-col gap-3 p-3.5">
            @if ($errors->any())<div class="rounded-md bg-bad-bg px-3 py-2.5 text-bad">{{ $errors->first() }}</div>@endif
            @switch($tab)
                @case('overview')
                    @php($ov = $this->overview)
                    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-line p-3">
                        <span class="flex-1"><span class="text-sm text-ink-muted">Store status</span><div class="text-lg font-semibold">{{ $data['status'] === 'live' ? 'Live' : 'Maintenance' }}</div></span>
                        <select wire:model="data.status" class="input w-auto"><option value="live">Live</option><option value="maintenance">Maintenance mode</option></select>
                    </div>
                    <x-ops.kpis><x-ops.kpi label="Visits · 7 days" :value="$ov['visits'] !== null ? ops_num($ov['visits']) : '—'" /><x-ops.kpi label="Website orders · 7 days" :value="$ov['orders']" tone="ok" /><x-ops.kpi label="Conversion" :value="$ov['conversion'] !== null ? $ov['conversion'].'%' : '—'" /><x-ops.kpi label="Setup progress" :value="$ov['progress'].'%'" :tone="$ov['progress'] === 100 ? 'ok' : 'warn'" /></x-ops.kpis>
                    <div class="rounded-lg border border-line"><div class="h-1.5 bg-soft"><div class="h-1.5 bg-brand" style="width: {{ $ov['progress'] }}%"></div></div>
                        @foreach ($ov['steps'] as $step => $done)<div class="flex items-center gap-2.5 border-b border-soft px-3 py-2 text-sm last:border-0"><span @class(['grid h-5 w-5 place-items-center rounded-full text-xs text-white', 'bg-brand' => $done, 'bg-line-2' => ! $done])>{{ $done ? '✓' : '' }}</span>{{ $step }}</div>@endforeach</div>
                @break
                @case('homepage')
                    <div class="rounded-lg border border-line"><div class="flex items-center justify-between border-b border-soft px-3 py-2"><span class="text-sm font-semibold">Banners</span><button type="button" wire:click="addBanner" class="btn-secondary btn-sm">+ Add banner</button></div>
                        @foreach ($data['banners'] as $i => $b)<div class="grid items-center gap-2 border-b border-soft px-3 py-2 last:border-0 sm:grid-cols-[auto_1fr_1fr_auto_auto]"><span class="flex flex-col"><button type="button" wire:click="moveBanner({{ $i }}, -1)" class="text-xs text-ink-muted" @disabled($loop->first)>▲</button><button type="button" wire:click="moveBanner({{ $i }}, 1)" class="text-xs text-ink-muted" @disabled($loop->last)>▼</button></span><input wire:model="data.banners.{{ $i }}.title" class="input py-1" placeholder="Title"><input wire:model="data.banners.{{ $i }}.link" class="input mono py-1" placeholder="/link"><label class="flex items-center gap-1.5 text-sm"><input type="checkbox" wire:model="data.banners.{{ $i }}.on" class="accent-brand"> On</label><button type="button" wire:click="removeBanner({{ $i }})" class="text-bad">×</button></div>@endforeach</div>
                    <div class="rounded-lg border border-line"><div class="border-b border-soft px-3 py-2 text-sm font-semibold">Sections</div>
                        @foreach ($data['sections'] as $k => $sec)<div class="flex items-center gap-3 border-b border-soft px-3 py-2 last:border-0"><label class="flex flex-1 items-center gap-2"><input type="checkbox" wire:model="data.sections.{{ $k }}.on" class="h-4 w-4 accent-brand"> {{ $sec['label'] }}@if (! empty($sec['note']))<span class="text-xs text-ink-muted">({{ $sec['note'] }})</span>@endif</label><span class="text-xs text-ink-muted">Show</span><input type="number" min="1" max="24" wire:model="data.sections.{{ $k }}.count" class="input w-20 py-1"></div>@endforeach</div>
                @break
                @case('pages')
                    @foreach ($data['pages'] as $i => $p)
                        <div class="rounded-lg border border-line">
                            <button type="button" wire:click="$set('page', {{ $page === $i ? 'null' : $i }})" class="flex w-full items-center justify-between px-3 py-2.5 text-left"><span class="font-medium">{{ $p['title'] }} <span class="mono text-xs text-ink-muted">/{{ $p['slug'] }}</span></span><span class="flex items-center gap-2">@if (trim((string) $p['body']) === '')<x-ops.badge tone="warn">Empty</x-ops.badge>@endif<x-ops.badge :tone="$p['on'] ? 'ok' : 'mute'">{{ $p['on'] ? 'On' : 'Off' }}</x-ops.badge></span></button>
                            @if ($page === $i)<div class="grid gap-2 border-t border-soft p-3 sm:grid-cols-[1fr_1fr_auto]"><x-ops.field label="Title"><input wire:model="data.pages.{{ $i }}.title" class="input"></x-ops.field><x-ops.field label="Slug"><input wire:model="data.pages.{{ $i }}.slug" class="input mono"></x-ops.field><label class="flex items-center gap-2 pt-5"><input type="checkbox" wire:model="data.pages.{{ $i }}.on" class="accent-brand"> On</label><x-ops.field label="Body" class="sm:col-span-3"><textarea wire:model="data.pages.{{ $i }}.body" rows="8" class="input"></textarea></x-ops.field></div>@endif
                        </div>
                    @endforeach
                @break
                @case('coupons')
                    <div class="overflow-x-auto rounded-lg border border-line"><table class="w-full min-w-[900px] text-sm"><tr><th class="th">Code</th><th class="th">Type</th><th class="th">Value</th><th class="th">Min order (৳)</th><th class="th">Usage limit</th><th class="th text-right">Used</th><th class="th">Expiry</th><th class="th">On</th><th class="th"></th></tr>
                        @foreach ($data['coupons'] as $i => $c)<tr class="border-b border-soft" wire:key="c{{ $i }}"><td class="px-2 py-1.5"><input wire:model="data.coupons.{{ $i }}.code" class="input mono py-1 uppercase"></td><td class="px-2 py-1.5"><select wire:model.live="data.coupons.{{ $i }}.type" class="input py-1">@foreach (config('ops.coupon_types') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></td><td class="px-2 py-1.5"><input type="number" min="0" wire:model="data.coupons.{{ $i }}.value" class="input w-20 py-1" @disabled($c['type'] === 'ship')></td><td class="px-2 py-1.5"><input type="number" min="0" wire:model="data.coupons.{{ $i }}.min" class="input w-24 py-1"></td><td class="px-2 py-1.5"><input type="number" min="0" wire:model="data.coupons.{{ $i }}.limit" class="input w-20 py-1" placeholder="∞"></td><td class="px-2 py-1.5 text-right">{{ $c['used'] ?? 0 }}</td><td class="px-2 py-1.5"><x-ops.date wire:model="data.coupons.{{ $i }}.expires" class="w-32 py-1" placeholder="No expiry" /></td><td class="px-2 py-1.5"><input type="checkbox" wire:model="data.coupons.{{ $i }}.on" class="h-4 w-4 accent-brand"></td><td class="px-2 py-1.5"><button type="button" wire:click="removeCoupon({{ $i }})" class="text-bad">×</button></td></tr>@endforeach
                    </table></div>
                    <button type="button" wire:click="addCoupon" class="btn-secondary btn-sm self-start">+ Add coupon</button>
                @break
                @case('social')
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (['facebook' => 'Facebook page URL', 'instagram' => 'Instagram URL', 'youtube' => 'YouTube URL', 'tiktok' => 'TikTok URL'] as $k => $l)<x-ops.field :label="$l"><input wire:model="data.social.{{ $k }}" class="input" placeholder="https://"></x-ops.field>@endforeach
                        <x-ops.field label="WhatsApp number" hint="Used by every WhatsApp link and message on the website" :error="$errors->first('data.social.whatsapp')"><input wire:model="data.social.whatsapp" class="input mono" placeholder="01XXXXXXXXX"></x-ops.field>
                        <x-ops.field label="Messenger" hint="Page username or m.me link"><input wire:model="data.social.messenger" class="input mono" placeholder="yourpage"></x-ops.field>
                        <x-ops.field label="Call number"><input wire:model="data.social.phone" class="input mono" placeholder="01XXXXXXXXX"></x-ops.field>
                        <div class="flex flex-col gap-2 pt-1"><span class="text-sm text-ink-muted">Floating chat buttons (bottom right of the website)</span>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="data.social.whatsapp_on" class="h-4 w-4 accent-brand"> WhatsApp</label>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="data.social.messenger_on" class="h-4 w-4 accent-brand"> Messenger</label>
                            <label class="flex items-center gap-2"><input type="checkbox" wire:model="data.social.call_on" class="h-4 w-4 accent-brand"> Call</label></div>
                    </div>
                @break
                @case('announcement')
                    <div class="rounded-lg border border-line p-3"><div class="mb-2 flex items-center justify-between"><span class="text-sm font-semibold">Announcement bar</span><label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="data.announcement.on" class="accent-brand"> On</label></div>
                        <div class="grid gap-2 sm:grid-cols-[1fr_200px]"><input wire:model.live.debounce.300ms="data.announcement.text" class="input" placeholder="Free delivery on orders over ৳2,000"><input wire:model="data.announcement.link" class="input mono" placeholder="/shop"></div>
                        @if ($data['announcement']['text'])<div class="mt-2 rounded bg-ink px-3 py-1.5 text-center text-sm text-white">{{ $data['announcement']['text'] }}</div>@endif</div>
                    <div class="rounded-lg border border-line p-3"><div class="mb-2 flex items-center justify-between"><span class="text-sm font-semibold">Popup</span><label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="data.popup.on" class="accent-brand"> On</label></div>
                        <div class="grid gap-2 sm:grid-cols-2"><x-ops.field label="Title"><input wire:model="data.popup.title" class="input"></x-ops.field><x-ops.field label="Coupon"><select wire:model="data.popup.coupon" class="input"><option value="">None</option>@foreach ($data['coupons'] as $c)<option>{{ $c['code'] }}</option>@endforeach</select></x-ops.field><x-ops.field label="Body" class="sm:col-span-2"><textarea wire:model="data.popup.body" rows="2" class="input"></textarea></x-ops.field><x-ops.field label="Delay (seconds)"><input type="number" min="0" wire:model="data.popup.delay" class="input"></x-ops.field><x-ops.field label="Frequency"><select wire:model="data.popup.frequency" class="input"><option value="once">Once</option><option value="every">Every visit</option></select></x-ops.field></div></div>
                @break
            @endswitch
            <p class="text-xs text-ink-muted">Theme, colours, fonts, payment gateways, pixels, SEO and branding live in Settings.</p>
        </div>
        <div class="sticky bottom-0 flex items-center justify-end gap-2 border-t border-soft bg-zebra px-3.5 py-3 max-[479px]:bottom-16">
            <span x-cloak x-show="dirty" class="mr-auto text-sm text-warn">● Unsaved changes</span>
            <button type="button" x-show="dirty" x-cloak wire:click="discard" class="btn-secondary">Discard</button>
            <button class="btn-primary">Publish</button>
        </div>
    </form>
    @endif
</div>
