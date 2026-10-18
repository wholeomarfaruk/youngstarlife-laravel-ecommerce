@php($nav = \App\Livewire\Ops\Settings\Index::NAV)
<div class="grid items-start gap-3 lg:grid-cols-[230px_minmax(0,1fr)]" x-data="{ dirty: false }" x-on:saved.window="dirty = false" x-init="window.opsDirty = false; $watch('dirty', v => window.opsDirty = v)">
    <nav class="card flex max-h-[260px] flex-col gap-0.5 overflow-y-auto p-1.5 lg:sticky lg:top-[72px] lg:max-h-[calc(100vh-90px)]">
        @foreach ($nav as $group => $items)
            <span class="px-2.5 pb-0.5 pt-2 text-2xs font-semibold uppercase tracking-wide text-ink-muted">{{ $group }}</span>
            @foreach ($items as $k => $l)<button type="button" @click="if (!dirty || confirm('Discard unsaved changes?')) { dirty = false; $wire.set('tab', '{{ $k }}') }" @class(['rounded px-2.5 py-1.5 text-left', 'bg-soft font-semibold' => $tab === $k, 'text-ink-3 hover:bg-zebra' => $tab !== $k])>{{ $l }}</button>@endforeach
        @endforeach
        <span class="px-2.5 pb-0.5 pt-2 text-2xs font-semibold uppercase tracking-wide text-ink-muted">You</span>
        <a href="{{ route('ops.profile') }}" wire:navigate class="rounded px-2.5 py-1.5 text-ink-3 hover:bg-zebra">My profile</a>
    </nav>

    {{-- AI order entry saves on its own (own component), outside the settings form --}}
    @if ($tab === 'ai_order')
        <livewire:ops.settings.ai-order />
    @else
    <form wire:submit="save" class="card" @input="dirty = true" @change="dirty = true">
        <div class="card-head"><span class="card-title">{{ $this->tabLabel() }}</span></div>
        <div class="flex flex-col gap-3 p-3.5">
            @if ($errors->any())<div class="rounded-md bg-bad-bg px-3 py-2.5 text-bad">{{ $errors->first() }}</div>@endif
            @switch($tab)
                {{-- ───── General ───── --}}
                @case('company')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ops.field label="Business name" required><input wire:model="s.company.name" class="input"></x-ops.field>
                        <x-ops.field label="Phone"><input wire:model="s.company.phone" class="input"></x-ops.field>
                        <x-ops.field label="Email"><input type="email" wire:model="s.company.email" class="input"></x-ops.field>
                        <x-ops.field label="Default warehouse"><select wire:model="s.company.warehouse_id" class="input"><option value="">—</option>@foreach ($warehouses as $w)<option value="{{ $w['id'] }}">{{ $w['name'] }}</option>@endforeach</select></x-ops.field>
                        <x-ops.field label="Address" class="sm:col-span-2"><textarea wire:model="s.company.address" rows="2" class="input"></textarea></x-ops.field>
                        <x-ops.field label="Currency"><select wire:model="s.company.currency" class="input"><option>BDT (৳)</option></select></x-ops.field>
                        <x-ops.field label="Invoice prefix"><input wire:model="s.company.invoice_prefix" class="input mono"></x-ops.field>
                        <x-ops.field label="Time zone"><select wire:model="s.company.timezone" class="input"><option>Asia/Dhaka</option></select></x-ops.field>
                    </div>
                    <div class="rounded-md border border-line"><div class="border-b border-soft px-3 py-2 text-sm font-semibold">Warehouses</div>
                        @foreach ($warehouses as $i => $w)<div class="grid gap-2 border-b border-soft px-3 py-2 sm:grid-cols-[1fr_1fr_auto]"><input wire:model="warehouses.{{ $i }}.name" class="input py-1"><input wire:model="warehouses.{{ $i }}.address" class="input py-1" placeholder="Address">@if ($w['is_default'])<x-ops.badge tone="ok">Default</x-ops.badge>@endif</div>@endforeach
                        <div class="flex gap-2 p-2.5"><input wire:model="newWarehouse" class="input py-1" placeholder="New warehouse, e.g. Uttara WH"><button type="button" wire:click="addWarehouse" class="btn-secondary btn-sm">+ Add</button></div></div>
                @break
                @case('orders')
                    @php($cartOn = (bool) ($s['orders']['cart_enabled'] ?? true))
                    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-md border border-line p-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-sm font-semibold">Website cart <x-ops.badge :tone="$cartOn ? 'ok' : 'warn'">{{ $cartOn ? 'On' : 'Off' }}</x-ops.badge></div>
                            <div class="text-xs text-ink-muted">{{ $cartOn ? 'Customers can add to cart and check out several products at once.' : 'Cart icon, drawer, "Add to cart" and /checkout are hidden. Customers order one product at a time from the product page form. Saved carts are kept.' }}</div>
                        </div>
                        @if ($cartOn)
                            <button type="button" wire:click="setCart(false)" wire:confirm="Disable the website cart?&#10;&#10;The cart icon, cart drawer, &quot;Add to cart&quot; button and checkout page disappear right away. Customers can still order from each product page. Items already in carts are kept for when you enable it again." class="btn-danger btn-sm">Disable cart</button>
                        @else
                            <button type="button" wire:click="setCart(true)" wire:confirm="Enable the website cart again?&#10;&#10;Customers will see the cart icon and &quot;Add to cart&quot; button, and their earlier carts come back." class="btn-primary btn-sm">Enable cart</button>
                        @endif
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ops.field label="Order prefix" required hint="#1042"><input wire:model="s.orders.prefix" class="input mono w-28"></x-ops.field>
                        <x-ops.field label="Auto-cancel unconfirmed after (hours)" hint="0 = never"><input type="number" min="0" wire:model="s.orders.auto_cancel_hours" class="input"></x-ops.field>
                        <x-ops.field label="Free delivery above (৳)"><input type="number" min="0" wire:model="s.orders.free_delivery_above" class="input"></x-ops.field>
                        <x-ops.field label="Exchange charge (৳)" required><input type="number" min="0" wire:model="s.orders.exchange_charge" class="input"></x-ops.field>
                        <x-ops.field label="Parcel stuck after (days without a courier update)" hint="Flagged on the order page and in In courier → Needs attention"><input type="number" min="1" max="30" wire:model="s.orders.parcel_stuck_days" class="input"></x-ops.field>
                        <x-ops.field label="Exchange old item overdue after (days)" hint="Alert when the old item is not back this long after the new one was delivered"><input type="number" min="1" wire:model="s.orders.exchange_overdue_days" class="input"></x-ops.field>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.orders.confirm_call" class="h-4 w-4 accent-brand"> Confirmation call required</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.orders.advance_for_risky" class="h-4 w-4 accent-brand"> Advance payment for risky customers</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.orders.allow_partial" class="h-4 w-4 accent-brand"> Allow partial delivery</label>
                    </div>
                    {{-- website order limits: CartService::room() — add to cart, cart quantity, product page order and checkout --}}
                    <div class="mt-4 rounded-md border border-line" x-data="{ type: $wire.entangle('s.orders.limit.type') }">
                        <div class="border-b border-soft px-3 py-2"><div class="text-sm font-semibold">Website order limits</div><div class="text-xs text-ink-muted">Checked on add to cart, cart quantity changes, the product page order form and checkout. Orders made here in Ops are not limited.</div></div>
                        <div class="grid gap-3 p-3 sm:grid-cols-3">
                            <x-ops.field label="Limit per product" hint="All sizes of one product together">
                                <select x-model="type" class="input"><option value="off">No limit</option><option value="qty">By quantity</option><option value="value">By value (৳)</option></select>
                            </x-ops.field>
                            <div x-show="type === 'qty'" x-cloak><x-ops.field label="Max quantity per product" hint="1–50 pcs"><input type="number" min="1" max="50" wire:model="s.orders.limit.qty" class="input"></x-ops.field></div>
                            <div x-show="type === 'value'" x-cloak><x-ops.field label="Max value per product (৳)" hint="One piece is always allowed, even if it costs more"><input type="number" min="1" wire:model="s.orders.limit.value" class="input"></x-ops.field></div>
                            <div x-show="type === 'off'" x-cloak class="hidden sm:block"></div>
                            <x-ops.field label="Max cart / order value (৳)" hint="0 = no limit"><input type="number" min="0" wire:model="s.orders.limit.cart_value" class="input"></x-ops.field>
                        </div>
                    </div>
                @break
                @case('delivery')
                    @foreach ($zones as $i => $z)
                        <div class="grid items-end gap-2 rounded-lg border border-line p-2.5 sm:grid-cols-[1fr_100px_90px_120px_140px_60px]">
                            <x-ops.field label="Zone"><input wire:model="zones.{{ $i }}.name" class="input"></x-ops.field>
                            <x-ops.field label="Charge (৳)"><input type="number" min="0" wire:model="zones.{{ $i }}.charge" class="input"></x-ops.field>
                            <x-ops.field label="Per kg (৳)"><input type="number" min="0" wire:model="zones.{{ $i }}.per_kg" class="input"></x-ops.field>
                            <x-ops.field label="ETA"><input wire:model="zones.{{ $i }}.eta" class="input" placeholder="1–2 days"></x-ops.field>
                            <x-ops.field label="Default courier"><select wire:model="zones.{{ $i }}.default_courier" class="input"><option value="">Auto</option>@foreach ($zonesCouriers as $code => $n)<option value="{{ $code }}">{{ $n }}</option>@endforeach</select></x-ops.field>
                            <label class="flex items-center gap-2 pb-2"><input type="checkbox" wire:model="zones.{{ $i }}.is_active" class="accent-brand"> On</label>
                            <x-ops.field label="Areas (comma separated)" class="sm:col-span-6"><input wire:model="zones.{{ $i }}.areas" class="input" placeholder="Mirpur, Dhanmondi, Uttara…"></x-ops.field>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addZone" class="btn-secondary btn-sm self-start">+ Add area</button>
                @break
                @case('payments')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.payments.cash" class="h-4 w-4 accent-brand"> Cash</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.payments.cod" class="h-4 w-4 accent-brand"> Cash on delivery (COD)</label>
                        <div class="flex flex-col gap-1.5"><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.payments.bank" class="h-4 w-4 accent-brand"> Bank transfer</label><input wire:model="s.payments.bank_account" class="input" placeholder="Bank, account no."></div>
                        <div class="flex flex-col gap-1.5"><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.payments.bkash" class="h-4 w-4 accent-brand"> bKash</label><input wire:model="s.payments.bkash_no" class="input mono" placeholder="Merchant number"></div>
                        <div class="flex flex-col gap-1.5"><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.payments.nagad" class="h-4 w-4 accent-brand"> Nagad</label><input wire:model="s.payments.nagad_no" class="input mono" placeholder="Number"></div>
                    </div>
                @break

                {{-- ───── Marketing ───── --}}
                @case('branding')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ops.field label="Store name" required><input wire:model="s.branding.store_name" class="input"></x-ops.field>
                        <x-ops.field label="Tagline"><input wire:model="s.branding.tagline" class="input"></x-ops.field>
                        @foreach (['logo' => 'Logo', 'favicon' => 'Favicon'] as $f => $l)
                            <div class="flex items-center gap-3 rounded-md border border-line p-2.5">
                                @if ($$f)<img src="{{ $$f->temporaryUrl() }}" class="h-12 w-12 rounded object-contain" alt="">@elseif ($s['branding'][$f] ?? null)<img src="{{ asset('storage/'.$s['branding'][$f]) }}" class="h-12 w-12 rounded object-contain" alt="">@else<img src="{{ asset('frontend/img/youngstar logo-circle.png') }}" class="h-12 w-12 rounded object-contain opacity-70" alt="" title="Built-in image">@endif
                                <div class="flex flex-col gap-1">
                                    <label class="btn-secondary btn-sm cursor-pointer">Upload {{ strtolower($l) }}<input type="file" wire:model="{{ $f }}" accept="image/*" class="sr-only"></label>
                                    @if ($s['branding'][$f] ?? null)<button type="button" wire:click="clearImage('{{ $f }}')" wire:confirm="Go back to the built-in {{ strtolower($l) }}?" class="btn-ghost btn-sm text-bad">Use built-in</button>@else<span class="text-xs text-ink-muted">Built-in image</span>@endif
                                </div>
                            </div>
                        @endforeach
                        <x-ops.field label="Brand colour" hint="Header, buttons and headings on the website" :error="$errors->first('s.branding.primary_color')">
                            <div class="flex gap-2"><input type="color" wire:model.live="s.branding.primary_color" class="h-9 w-12 cursor-pointer rounded border border-line-2 bg-white p-0.5"><input wire:model.live.debounce.400ms="s.branding.primary_color" class="input mono" placeholder="#525754"></div>
                        </x-ops.field>
                        <x-ops.field label="Footer note" hint="Shown under the footer links. {year} {store} work, e.g. © {year} {store}"><input wire:model="s.branding.footer_note" class="input"></x-ops.field>
                    </div>
                    <div class="flex items-center gap-3 rounded-md px-3 py-2.5 text-white" style="background: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($s['branding']['primary_color'] ?? '')) ? $s['branding']['primary_color'] : '#525754' }}">
                        <img src="{{ $logo ? $logo->temporaryUrl() : (($s['branding']['logo'] ?? null) ? asset('storage/'.$s['branding']['logo']) : asset('frontend/img/youngstar logo-circle.png')) }}" class="h-9 w-9 rounded-full bg-white object-contain" alt="">
                        <span class="text-lg font-semibold">{{ $s['branding']['store_name'] }}</span><span class="ml-auto text-xs opacity-80">Website header preview</span>
                    </div>
                @break
                @case('seo')
                    <x-ops.field label="Home title" :hint="mb_strlen($s['seo']['title'] ?? '').' / 60 · also used when a page has no title of its own'" :error="$errors->first('s.seo.title')"><input wire:model.live.debounce.400ms="s.seo.title" class="input"></x-ops.field>
                    <x-ops.field label="Page title format" :hint="'Product, category and other pages: '.strtr($s['seo']['title_format'] ?? '', ['{page}' => 'Cargo Pant', '{site}' => $s['branding']['store_name'] ?? ''])" :error="$errors->first('s.seo.title_format')"><input wire:model.live.debounce.400ms="s.seo.title_format" class="input mono" placeholder="{page} · {site}"></x-ops.field>
                    <x-ops.field label="Description" :hint="strlen($s['seo']['description'] ?? '').' / 160'"><textarea wire:model.live.debounce.400ms="s.seo.description" rows="3" class="input"></textarea></x-ops.field>
                    <div class="flex flex-wrap items-end gap-3">
                        <x-ops.field label="Share image" hint="Facebook / WhatsApp link preview. Product pages use the product photo."><select wire:model.live="s.seo.share_image" class="input w-auto"><option value="logo">Logo</option><option value="custom">Custom image</option></select></x-ops.field>
                        @if (($s['seo']['share_image'] ?? 'logo') === 'custom')
                            @if ($shareImage)<img src="{{ $shareImage->temporaryUrl() }}" class="h-14 w-24 rounded border border-line object-cover" alt="">@elseif ($s['seo']['share_image_path'] ?? null)<img src="{{ asset('storage/'.$s['seo']['share_image_path']) }}" class="h-14 w-24 rounded border border-line object-cover" alt="">@endif
                            <label class="btn-secondary btn-sm cursor-pointer">Upload 1200×630 image<input type="file" wire:model="shareImage" accept="image/*" class="sr-only"></label>
                        @endif
                    </div>
                    <x-ops.field label="Search Console verification code"><input wire:model="s.seo.search_console" class="input mono"></x-ops.field>
                    <div class="flex flex-wrap gap-4"><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.seo.sitemap" class="h-4 w-4 accent-brand"> <a href="{{ route('sitemap') }}" target="_blank" class="mono">sitemap.xml</a></label><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.seo.indexable" class="h-4 w-4 accent-brand"> Allow search engines to index</label></div>
                    <div class="rounded-md border border-line p-3"><div class="text-xs text-ink-muted">Google preview</div><div class="truncate text-lg text-info">{{ $s['seo']['title'] }}</div><div class="mono text-xs text-ok">{{ config('app.url') }}</div><div class="text-sm text-ink-3">{{ $s['seo']['description'] }}</div></div>
                @break
                @case('pixels')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ops.field label="GTM container" hint="The website's main tag manager (events go to its dataLayer)" :error="$errors->first('s.pixels.gtm')"><input wire:model="s.pixels.gtm" class="input mono" placeholder="GTM-XXXXXX"></x-ops.field>
                        <x-ops.field label="Facebook domain verification" hint="Business Manager → Brand safety → Domains"><input wire:model="s.pixels.fb_domain_verification" class="input mono"></x-ops.field>
                        <x-ops.field label="Facebook Pixel ID" hint="Leave empty when the pixel already runs inside GTM — otherwise events count twice" :error="$errors->first('s.pixels.fb_pixel')"><input wire:model="s.pixels.fb_pixel" class="input mono"></x-ops.field>
                        <x-ops.field label="GA4 Measurement ID" hint="Leave empty when GA4 already runs inside GTM" :error="$errors->first('s.pixels.ga4')"><input wire:model="s.pixels.ga4" class="input mono" placeholder="G-XXXXXXX"></x-ops.field>
                        <x-ops.field label="TikTok Pixel"><input wire:model="s.pixels.tiktok" class="input mono"></x-ops.field>
                        <x-ops.field label="Brand in ecommerce events" hint="item_brand on view / add to cart / purchase"><input wire:model="s.pixels.item_brand" class="input"></x-ops.field>
                        <x-ops.field label="Category in ecommerce events" hint="item_category on view / add to cart / purchase"><input wire:model="s.pixels.item_category" class="input"></x-ops.field>
                        <label class="flex items-center gap-2 self-end pb-2"><input type="checkbox" wire:model="s.pixels.production_only" class="h-4 w-4 accent-brand"> Load tags on the live site only (not on local / test copies)</label>
                    </div>
                    <div class="rounded-md border border-dashed border-line-2 p-3">
                        <div class="mb-2 text-sm text-ink-muted">Server-side Conversions API — saved here, <b>not sent yet</b> (no server-side sender is built).</div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-ops.field label="Conversions API token"><input type="password" wire:model="s.pixels.capi_token" class="input mono" autocomplete="off"></x-ops.field>
                            <x-ops.field label="Test event code"><input wire:model="s.pixels.test_event" class="input mono"></x-ops.field>
                            <x-ops.field label="Send Purchase event on"><select wire:model="s.pixels.purchase_on" class="input"><option value="placed">Order placed</option><option value="confirmed">Order confirmed</option><option value="delivered">Order delivered</option></select></x-ops.field>
                        </div>
                    </div>
                @break

                {{-- ───── Integrations ───── --}}
                @case('webhooks')
                    @foreach ($couriers as $i => $c)
                        <div class="flex flex-col gap-2 rounded-lg border border-line p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold">{{ $c['name'] }}</span><span class="text-xs text-ink-muted">last received {{ $c['last'] ? \Carbon\Carbon::parse($c['last'])->diffForHumans() : 'never' }}</span><label class="flex items-center gap-2"><input type="checkbox" wire:model="couriers.{{ $i }}.webhook_on" class="accent-brand"> On</label></div>
                            <x-ops.field label="Callback URL" hint="Paste this and the secret in the courier's merchant panel — step-by-step in Settings → Couriers"><div class="flex gap-1.5"><input readonly value="{{ $c['url'] }}" class="input mono bg-zebra text-xs"><button type="button" onclick="opsCopy(@js($c['url']))" class="btn-secondary btn-sm">Copy</button></div></x-ops.field>
                            <x-ops.field :label="$c['secret_label']"><div class="flex gap-1.5" x-data="{ show: false }"><input readonly :type="show ? 'text' : 'password'" value="{{ $c['secret'] }}" class="input mono bg-zebra text-xs"><button type="button" @click="show = !show" class="btn-secondary btn-sm" x-text="show ? 'Hide' : 'Show'"></button><button type="button" wire:click="rotateSecret({{ $c['id'] }})" wire:confirm="Rotate the secret? The courier must be updated with the new one." class="btn-secondary btn-sm">Rotate</button></div></x-ops.field>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm"><span class="text-xs text-ink-muted">Subscribed events:</span>@foreach (config('ops.courier_status') as $k => [$l])<label class="flex items-center gap-1"><input type="checkbox" value="{{ $k }}" wire:model="couriers.{{ $i }}.events" class="accent-brand"> {{ $l }}</label>@endforeach</div>
                        </div>
                    @endforeach
                    <div class="rounded-lg border border-line"><div class="border-b border-soft px-3 py-2 text-sm font-semibold">Delivery log</div>
                        @forelse ($this->webhookLog as $w)<div class="grid grid-cols-[90px_80px_1fr_50px_60px] gap-2 border-b border-soft px-3 py-1.5 text-xs last:border-0"><span class="text-ink-muted">{{ $w->created_at->format('d M H:i:s') }}</span><span>{{ $w->courier?->name ?? '—' }}</span><span class="mono">{{ $w->event }}</span><span @class(['mono font-semibold', 'text-ok' => $w->status_code < 300, 'text-bad' => $w->status_code >= 400])>{{ $w->status_code }}</span><span class="text-right text-ink-muted">{{ $w->result ?? '' }} {{ $w->ms }} ms</span></div>
                        @empty<div class="p-3 text-sm text-ink-muted">No webhook calls received yet.</div>@endforelse</div>
                    <div class="rounded-lg border border-line p-3"><div class="mb-2 text-sm font-semibold">Outgoing events</div>
                        <x-ops.field label="Send to URL"><input wire:model="s.webhooks.outgoing_url" class="input mono" placeholder="https://…"></x-ops.field>
                        <div class="mt-2 flex flex-wrap gap-4">@foreach (['order.created' => 'Order created', 'order.status' => 'Order status changed', 'return.received' => 'Return received'] as $k => $l)<label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="s.webhooks.outgoing.{{ $k }}" class="accent-brand"> {{ $l }}</label>@endforeach</div></div>
                @break
                @case('push')
                    <div x-data="{ perm: typeof Notification !== 'undefined' ? Notification.permission : 'unsupported' }" class="flex flex-wrap items-center gap-2 rounded-lg border border-line p-3">
                        <span class="flex-1 text-sm">This device: <b x-text="perm === 'granted' ? 'Enabled' : (perm === 'denied' ? 'Blocked in browser settings' : (perm === 'unsupported' ? 'Not supported' : 'Not enabled'))"></b></span>
                        <button type="button" class="btn-secondary btn-sm" @click="Notification.requestPermission().then(p => perm = p)" x-show="perm === 'default'">Enable on this device</button>
                        <button type="button" class="btn-secondary btn-sm" x-show="perm === 'granted'" @click="new Notification('YoungStar Ops', { body: 'Test notification — push works on this device.' })">Send test</button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-line"><table class="w-full text-sm"><tr><th class="th">Event</th><th class="th text-center">In-app</th><th class="th text-center">Push</th><th class="th text-center">Email</th></tr>
                        @foreach (config('ops.notify_events') as $k => [$l])<tr class="border-b border-soft"><td class="px-3 py-2">{{ $l }} <span class="mono text-2xs text-ink-muted">{{ $k }}</span></td>@foreach (['app', 'push', 'email'] as $ch)<td class="text-center"><input type="checkbox" wire:model="s.push.matrix.{{ $k }}.{{ $ch }}" class="h-4 w-4 accent-brand"></td>@endforeach</tr>@endforeach
                    </table></div>
                @break
                @case('smtp')
                    <div class="flex flex-wrap gap-1.5">@foreach (['gmail' => 'Gmail', 'zoho' => 'Zoho', 'outlook' => 'Outlook', 'cpanel' => 'cPanel'] as $k => $l)<button type="button" wire:click="smtpPreset('{{ $k }}')" @class(['chip', 'chip-on' => ($s['smtp']['preset'] ?? '') === $k])>{{ $l }}</button>@endforeach
                        <span class="ml-auto">@if ($s['smtp']['verified'] ?? false)<x-ops.badge tone="ok">Connected</x-ops.badge>@else<x-ops.badge tone="warn">Not verified</x-ops.badge>@endif</span></div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <x-ops.field label="Host" class="sm:col-span-2"><input wire:model="s.smtp.host" class="input mono"></x-ops.field>
                        <x-ops.field label="Port"><input type="number" wire:model="s.smtp.port" class="input"></x-ops.field>
                        <x-ops.field label="Encryption"><select wire:model="s.smtp.encryption" class="input"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="none">None</option></select></x-ops.field>
                        <x-ops.field label="Username"><input wire:model="s.smtp.username" class="input" autocomplete="off"></x-ops.field>
                        <x-ops.field label="Password"><input type="password" wire:model="s.smtp.password" class="input" autocomplete="new-password"></x-ops.field>
                        <x-ops.field label="From name"><input wire:model="s.smtp.from_name" class="input"></x-ops.field>
                        <x-ops.field label="From email" :error="$errors->first('s.smtp.from_email')"><input type="email" wire:model="s.smtp.from_email" class="input"></x-ops.field>
                        <x-ops.field label="Reply-to"><input type="email" wire:model="s.smtp.reply_to" class="input"></x-ops.field>
                    </div>
                    <button type="button" wire:click="testSmtp" wire:loading.attr="disabled" class="btn-secondary self-start">Send test email to {{ auth()->user()->email }}</button>
                @break

                {{-- ───── Operations ───── --}}
                @case('automations')
                    @php($st = $this->automationStats)
                    <x-ops.kpis><x-ops.kpi label="Active rules" :value="$st['active']" tone="ok" /><x-ops.kpi label="Actions today" :value="$st['today']" /><x-ops.kpi label="Time saved" :value="$st['saved'].' min'" tone="ok" /><x-ops.kpi label="Needs a human" :value="$st['human']" tone="warn" :href="route('ops.orders.index', ['status' => 'on_hold'])" /></x-ops.kpis>
                    @foreach (['Orders' => ['auto_confirm', 'flag_risky', 'auto_courier', 'auto_allocate'], 'Courier & delivery' => ['auto_book', 'webhook_sync', 'courier_poll', 'sms_updates', 'auto_hold', 'delay_alert'], 'Returns & stock' => ['restock_good', 'draft_supplier_return', 'low_stock_po', 'courier_claim'], 'Money' => ['settle_match', 'supplier_reminder']] as $grp => $rules)
                        <div class="rounded-lg border border-line"><div class="border-b border-soft px-3 py-2 text-sm font-semibold">{{ $grp }}</div>
                            @foreach ($rules as $rk)
                                @php($rule = $s['automations'][$rk]) @php([$runs, $last] = $st['runs'][$rk] ?? [null, null])
                                <label class="flex items-center gap-3 border-b border-soft px-3 py-2.5 last:border-0">
                                    <input type="checkbox" wire:model="s.automations.{{ $rk }}.on" class="h-4 w-4 accent-brand">
                                    <span class="flex-1 text-sm"><span class="text-ink-muted">When</span> {{ $rule['when'] }} <span class="text-ink-muted">then</span> <b>{{ $rule['then'] }}</b>@if ($rk === 'sms_updates')<span class="ml-1 text-xs text-ink-muted">(SMS API not connected — demo)</span>@endif</span>
                                    <span class="text-right text-xs text-ink-muted">{{ $runs !== null ? $runs.' today' : '' }}<br>{{ $last ? 'last '.\Carbon\Carbon::parse($last)->diffForHumans() : '' }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                @break
                @case('batch')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ops.field label="Batch code format" required hint="B{###} → B014"><input wire:model="s.batch.format" class="input mono"></x-ops.field>
                        <x-ops.field label="Default low-stock threshold" required><input type="number" min="0" wire:model="s.batch.low_threshold" class="input"></x-ops.field>
                        <x-ops.field label="Reserve stock when"><select wire:model="s.batch.reserve_when" class="input"><option value="placed">Order placed</option><option value="confirmed">Order confirmed</option><option value="packed">Packed</option></select></x-ops.field>
                        <div class="flex flex-col gap-2 pt-5"><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.batch.create_on_receive" class="h-4 w-4 accent-brand"> Create a batch on every receive</label><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.batch.website_stock" class="h-4 w-4 accent-brand"> Website: sizes without free stock can't be ordered (off = keep selling, stock is checked when packing)</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.batch.scan_at_packing" class="h-4 w-4 accent-brand"> Packing must scan the batch sticker (SKU-B014), not the product barcode — records which batch / supplier each unit came from</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="s.batch.allow_negative" class="h-4 w-4 accent-brand"> Allow negative stock</label><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.batch.track_expiry" class="h-4 w-4 accent-brand"> Track expiry dates</label></div>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">@foreach (['FIFO' => 'First in, first out — oldest batch sells first', 'FEFO' => 'First expiry, first out — needs expiry dates', 'Manual' => 'Pick the batch at packing'] as $k => $d)
                        <label @class(['cursor-pointer rounded-md border p-2.5', 'border-brand bg-brand-tint' => ($s['batch']['allocation'] ?? 'FIFO') === $k, 'border-line-2' => ($s['batch']['allocation'] ?? 'FIFO') !== $k])><input type="radio" wire:model.live="s.batch.allocation" value="{{ $k }}" class="sr-only"><div class="font-semibold">{{ $k }}</div><div class="text-xs text-ink-muted">{{ $d }}</div></label>@endforeach</div>
                @break
                @case('barcode')
                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_240px]">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ops.field label="Product barcode"><select wire:model.live="s.barcode.product" class="input"><option>EAN-13</option><option>Code-128</option><option>QR</option></select></x-ops.field>
                            <x-ops.field label="Barcode prefix"><input wire:model="s.barcode.prefix" class="input mono"></x-ops.field>
                            <x-ops.field label="Batch label"><select wire:model.live="s.barcode.batch_label" class="input"><option value="code128">Code-128 · SKU-BATCH</option><option value="qr">QR · trace link</option></select></x-ops.field>
                            <x-ops.field label="Label size"><select wire:model.live="s.barcode.size" class="input"><option value="50x30">50 × 30 mm</option><option value="40x25">40 × 25 mm</option><option value="38x25x2">38 × 25 mm × 2</option></select></x-ops.field>
                            <x-ops.field label="Printer"><input wire:model="s.barcode.printer" class="input"></x-ops.field>
                            <div class="flex flex-col gap-2 pt-5"><label class="flex items-center gap-2"><input type="checkbox" wire:model.live="s.barcode.price_on_label" class="h-4 w-4 accent-brand"> Price on label</label><label class="flex items-center gap-2"><input type="checkbox" wire:model="s.barcode.parcel_label" class="h-4 w-4 accent-brand"> Print parcel label at packing</label></div>
                        </div>
                        <div><div class="mb-1 text-xs text-ink-muted">Live preview</div>
                            <div class="flex aspect-[5/3] w-full flex-col justify-between rounded border border-line-2 bg-white p-2"><div class="flex justify-between text-2xs font-bold"><span>{{ strtoupper($s['branding']['store_name'] ?? 'YOUNGSTAR LIFE') }}</span>@if ($s['barcode']['price_on_label'] ?? true)<span>৳1,250</span>@endif</div><div class="truncate text-xs font-semibold">Cargo Pant · Blue M</div>
                                @if (($s['barcode']['batch_label'] ?? 'code128') === 'qr')<div class="flex items-center gap-2"><span class="grid h-10 w-10 place-items-center border-2 border-ink text-2xs">QR</span><span class="mono text-2xs">CGO-BLU-M-B001</span></div>@else<div class="h-8 bg-[repeating-linear-gradient(90deg,#1A1A17_0_1px,transparent_1px_3px,#1A1A17_3px_5px,transparent_5px_6px)]"></div><span class="mono text-center text-2xs">CGO-BLU-M-B001</span>@endif</div>
                            <a href="{{ route('ops.labels.print', ['batches' => \App\Models\Ops\Batch::value('id'), 'q' => [], 'size' => $s['barcode']['size'] ?? '50x30']) }}" target="_blank" class="btn-secondary btn-sm mt-2 w-full">Print a test label</a></div>
                    </div>
                @break
                @case('returns')
                    <div class="rounded-lg border border-line"><div class="border-b border-soft px-3 py-2 text-sm font-semibold">Return reasons</div>
                        <div class="flex flex-wrap gap-1.5 p-3">@foreach ($s['returns']['reasons'] as $i => $r)<span class="chip">{{ $r }}<button type="button" wire:click="removeReason({{ $i }})" class="text-ink-muted">×</button></span>@endforeach</div>
                        <div class="flex gap-2 border-t border-soft p-2.5"><input wire:model="newReason" wire:keydown.enter.prevent="addReason" class="input py-1" placeholder="New reason"><button type="button" wire:click="addReason" class="btn-secondary btn-sm">+ Add</button></div></div>
                    <div class="rounded-lg border border-line"><div class="border-b border-soft px-3 py-2 text-sm font-semibold">Condition → action</div>
                        @foreach (config('ops.return_conditions') as $k => [$l, $a, $al, $tone])<div class="flex items-center justify-between border-b border-soft px-3 py-2 text-sm last:border-0"><span>{{ $l }}</span><x-ops.badge :tone="$tone">{{ $al }}{{ $a === 'restock' ? ' → original batch' : '' }}{{ in_array($k, ['damaged', 'broken']) ? ' + supplier return' : '' }}</x-ops.badge></div>@endforeach</div>
                @break
                @case('couriers')
                    <div x-on:input.stop x-on:change.stop><livewire:ops.settings.courier-setup /></div>
                @break

                {{-- ───── Team ───── --}}
                @case('users')
                    @if ($tempPassword)<div class="rounded-md bg-warn-bg px-3 py-2 text-sm text-warn">{{ $tempPassword }}</div>@endif
                    <div class="grid items-end gap-2 rounded-lg border border-line p-3 sm:grid-cols-[1fr_1fr_140px_auto]">
                        <x-ops.field label="Name" :error="$errors->first('invite.name')"><input wire:model="invite.name" class="input"></x-ops.field>
                        <x-ops.field label="Email" :error="$errors->first('invite.email')"><input type="email" wire:model="invite.email" class="input"></x-ops.field>
                        <x-ops.field label="Role"><select wire:model="invite.role" class="input">@foreach (config('ops.roles') as $r)<option>{{ $r }}</option>@endforeach</select></x-ops.field>
                        <button type="button" wire:click="inviteUser" class="btn-primary">Invite</button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border border-line"><table class="w-full min-w-[620px] text-sm"><tr><th class="th">User</th><th class="th">Role</th><th class="th">Active</th><th class="th">Last seen</th></tr>
                        @foreach ($this->users as $u)<tr class="border-b border-soft" wire:key="u{{ $u->id }}"><td class="px-3 py-2"><span class="font-medium">{{ $u->name }}</span><div class="text-xs text-ink-muted">{{ $u->email }}</div></td>
                            <td class="px-3 py-2"><select x-data @change="$wire.setRole({{ $u->id }}, $event.target.value)" class="input w-auto py-1" @disabled($u->id === auth()->id())>@foreach (config('ops.roles') as $r)<option @selected(($u->ops_role ?: ($u->role === 'admin' ? 'Admin' : '')) === $r)>{{ $r }}</option>@endforeach</select></td>
                            <td class="px-3 py-2"><button type="button" wire:click="toggleActive({{ $u->id }})" @class(['badge-ok' => $u->is_active, 'badge-mute' => ! $u->is_active])>{{ $u->is_active ? 'Active' : 'Inactive' }}</button></td>
                            <td class="px-3 py-2 text-ink-muted">{{ $u->last_seen_at?->diffForHumans() ?? 'never' }}</td></tr>@endforeach
                    </table></div>
                    <div class="overflow-x-auto rounded-lg border border-line"><div class="border-b border-soft px-3 py-2 text-sm font-semibold">Permissions · role × module</div><table class="w-full min-w-[640px] text-sm"><tr><th class="th">Module</th><th class="th text-center">Admin</th>@foreach (['Manager', 'Packer', 'Accounts'] as $r)<th class="th text-center">{{ $r }}</th>@endforeach</tr>
                        @foreach (config('ops.modules') as $m)<tr class="border-b border-soft"><td class="px-3 py-1.5">{{ $m }}</td><td class="text-center"><input type="checkbox" checked disabled class="accent-brand"></td>@foreach (['Manager', 'Packer', 'Accounts'] as $r)<td class="text-center"><input type="checkbox" value="{{ $m }}" wire:model="s.permissions.{{ $r }}" class="h-4 w-4 accent-brand"></td>@endforeach</tr>@endforeach
                    </table></div>
                @break
                @case('sms')
                    <p class="text-sm text-ink-muted">Variables: <span class="mono">{name} {order} {amount} {courier} {tracking}</span>. SMS gateway not connected yet — templates are saved and used once a gateway is added (demo).</p>
                    @foreach ($s['sms']['templates'] as $k => $t)
                        <div class="flex flex-col gap-2 rounded-lg border border-line p-3"><div class="flex items-center justify-between"><span class="font-semibold">{{ $t['name'] }}</span><span class="flex items-center gap-3 text-xs text-ink-muted">{{ $t['sent'] }} sent<label class="flex items-center gap-1.5 text-sm text-ink"><input type="checkbox" wire:model="s.sms.templates.{{ $k }}.on" class="accent-brand"> On</label></span></div>
                            <textarea wire:model.live.debounce.400ms="s.sms.templates.{{ $k }}.body" rows="2" class="input"></textarea><span class="text-xs text-ink-muted">{{ strlen($t['body']) }} / 160 · preview: {{ strtr($t['body'], ['{name}' => 'Nusrat', '{order}' => '#1042', '{amount}' => '৳2,450', '{courier}' => 'Steadfast', '{tracking}' => 'SF553357']) }}</span></div>
                    @endforeach
                @break
                @case('activity')
                    <div class="flex flex-wrap gap-2"><input wire:model.live.debounce.300ms="activitySearch" class="input flex-1" placeholder="Search activity…"><select wire:model.live="activityUser" class="input w-auto"><option value="">All users</option>@foreach ($this->users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                    <div class="rounded-lg border border-line">
                        @forelse ($this->activity as $a)<div class="grid grid-cols-[110px_minmax(0,1fr)_120px] gap-2.5 border-b border-soft px-3 py-2 text-sm last:border-0"><span class="mono text-xs text-ink-muted">{{ $a->created_at->format('d M H:i') }}</span><span>{{ $a->message }}</span><span class="text-right text-xs text-ink-muted">{{ $a->user?->name ?? 'System' }}</span></div>
                        @empty<div class="p-4 text-ink-muted">No activity.</div>@endforelse
                    </div>
                    <x-ops.pagination :paginator="$this->activity" />
                @break
                @case('content')
                    <p class="text-sm text-ink-muted">Words the website shows. <span class="mono">{store}</span> <span class="mono">{whatsapp}</span> <span class="mono">{phone}</span> <span class="mono">{email}</span> are filled in automatically — the WhatsApp number is set in <a href="{{ route('ops.storefront.index', ['tab' => 'social']) }}" wire:navigate>Storefront → Social & chat</a> ({{ site()->whatsapp() ?: 'not set' }}).</p>
                    <div class="rounded-md border border-line">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-soft px-3 py-2"><span class="text-sm font-semibold">FAQ box</span>
                            <div class="flex flex-wrap gap-3 text-sm">@foreach (['home' => 'Home', 'shop' => 'Shop', 'category' => 'Category', 'product' => 'Product'] as $k => $l)<label class="flex items-center gap-1.5"><input type="checkbox" wire:model="s.content.faq_on.{{ $k }}" class="accent-brand"> {{ $l }}</label>@endforeach</div></div>
                        <div class="flex flex-col gap-2 p-3">
                            <x-ops.field label="Heading"><input wire:model="s.content.faq_title" class="input"></x-ops.field>
                            @foreach ($s['content']['faq'] as $i => $line)
                                <div class="flex items-start gap-1.5" wire:key="faq-{{ $i }}">
                                    <span class="mono w-5 pt-2 text-xs text-ink-muted">{{ $i + 1 }}</span>
                                    <textarea wire:model="s.content.faq.{{ $i }}" rows="2" class="input"></textarea>
                                    <div class="flex flex-col"><button type="button" wire:click="moveFaq({{ $i }}, -1)" class="btn-ghost btn-sm" @disabled($i === 0)>↑</button><button type="button" wire:click="moveFaq({{ $i }}, 1)" class="btn-ghost btn-sm" @disabled($i === count($s['content']['faq']) - 1)>↓</button></div>
                                    <button type="button" wire:click="removeFaq({{ $i }})" class="btn-ghost btn-sm text-bad">✕</button>
                                </div>
                            @endforeach
                            <button type="button" wire:click="addFaq" class="btn-secondary btn-sm self-start">+ Add line</button>
                        </div>
                    </div>
                    <div class="rounded-md border border-line">
                        <div class="flex items-center justify-between border-b border-soft px-3 py-2"><span class="text-sm font-semibold">Product page note</span><label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="s.content.product_note.on" class="accent-brand"> Show</label></div>
                        <div class="grid gap-3 p-3 sm:grid-cols-[120px_1fr]">
                            <x-ops.field label="Label"><input wire:model="s.content.product_note.label" class="input"></x-ops.field>
                            <x-ops.field label="Link text" hint="Opens WhatsApp when a number is set"><input wire:model="s.content.product_note.text" class="input"></x-ops.field>
                            <x-ops.field label="Pre-filled WhatsApp message" class="sm:col-span-2"><input wire:model="s.content.product_note.whatsapp_text" class="input"></x-ops.field>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ops.field label="Checkout delivery note"><input wire:model="s.content.cod_note" class="input"></x-ops.field>
                        <x-ops.field label="Payment method on the order page"><input wire:model="s.content.payment_label" class="input"></x-ops.field>
                        <x-ops.field label="Thank-you text on the order page" class="sm:col-span-2"><textarea wire:model="s.content.order_thanks" rows="2" class="input"></textarea></x-ops.field>
                        <x-ops.field label="WhatsApp message from the order page" hint="{invoice} = order number"><input wire:model="s.content.order_whatsapp_text" class="input"></x-ops.field>
                        <x-ops.field label="Order failed message" required :error="$errors->first('s.content.order_failed')"><textarea wire:model="s.content.order_failed" rows="2" class="input"></textarea></x-ops.field>
                        <x-ops.field label="Same customer ordered again within 30 minutes" required class="sm:col-span-2" :error="$errors->first('s.content.order_duplicate')"><textarea wire:model="s.content.order_duplicate" rows="3" class="input"></textarea></x-ops.field>
                        <x-ops.field label="Maintenance mode page" required class="sm:col-span-2" hint="Shown while Storefront → Overview → Store status is Maintenance" :error="$errors->first('s.content.maintenance')"><textarea wire:model="s.content.maintenance" rows="2" class="input"></textarea></x-ops.field>
                    </div>
                @break

                @case('system')
                    @php($sys = $this->system)
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md border border-line p-3"><div class="text-xs text-ink-muted">Cache store</div><div class="mono text-lg font-semibold">{{ $sys['store'] }}</div></div>
                        <div class="rounded-md border border-line p-3"><div class="text-xs text-ink-muted">Saved settings groups</div><div class="text-lg font-semibold">{{ $sys['groups'] }}</div></div>
                        <div class="rounded-md border border-line p-3"><div class="text-xs text-ink-muted">Settings cache</div><div class="text-lg font-semibold">{{ $sys['cached'] ? 'Warm' : 'Empty (fills on next page view)' }}</div></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 rounded-md border border-line p-3">
                        <span class="flex-1 text-sm">All settings are read from one cache entry and refreshed automatically on every save. Clear it only after changing the database by hand.</span>
                        <button type="button" wire:click="clearCache" class="btn-secondary">Clear settings cache</button>
                    </div>
                    <div class="rounded-md border border-line">
                        <div class="border-b border-soft px-3 py-2 text-sm font-semibold">Backup & move settings</div>
                        <div class="flex flex-col gap-3 p-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" wire:click="exportSettings" class="btn-secondary">Download settings (.json)</button>
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="withSecrets" class="accent-brand"> Include passwords & tokens (SMTP password, Conversions API token)</label>
                            </div>
                            <div class="flex flex-wrap items-end gap-2 border-t border-soft pt-3">
                                <x-ops.field label="Restore from a backup" :error="$errors->first('backup')" hint="Replaces every group in the file. Missing passwords keep their current value." class="min-w-[220px] flex-1"><input type="file" wire:model="backup" accept=".json,application/json" class="input"></x-ops.field>
                                <button type="button" wire:click="importSettings" wire:confirm="Replace the current settings with the ones in this file?" class="btn-danger" @disabled(! $backup)>Import</button>
                            </div>
                            <div class="text-xs text-ink-muted">Saved groups: <span class="mono">{{ implode(', ', $sys['keys']) ?: '—' }}</span></div>
                        </div>
                    </div>
                @break
            @endswitch
        </div>
        @if (! in_array($tab, ['activity', 'system', 'couriers']))
            <div class="sticky bottom-0 flex items-center justify-end gap-2 border-t border-soft bg-zebra px-3.5 py-3 max-[479px]:bottom-16">
                <span x-cloak x-show="dirty" class="mr-auto text-sm text-warn">● Unsaved changes</span>
                <button type="button" x-show="dirty" x-cloak wire:click="discard" class="btn-secondary">Discard</button>
                <button class="btn-primary">Save changes</button>
            </div>
        @endif
    </form>
    @endif
</div>
