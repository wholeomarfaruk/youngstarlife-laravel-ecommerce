{{-- Product page info column (App\Livewire\Storefront\ProductBuy). Everything is rendered here once; Alpine (ysProduct) only switches state. --}}
@php($bn = fn ($n) => \App\Services\Storefront\ProductPage::bn($n))
@php($kind = $d['kind'])
@php($note = site('content.product_note'))
<div class="pp-info" x-data="ysProduct(@js($d))" x-init="boot()">
    {{-- title · price · trust --}}
    <div class="pp-stack-10">
        <h1 class="pp-title">{{ $p->name }}</h1>
        <div class="pp-price-row">
            <span class="pp-price">৳ <span x-text="price">{{ $d['price'] }}</span></span>
            {{-- rendered on the server (no layout shift), Alpine keeps it right when a size has its own price --}}
            <span class="pp-old" x-show="old > price" @if ($d['old'] <= $d['price']) style="display:none" @endif>৳ <span x-text="old">{{ $d['old'] }}</span></span>
            <span class="pp-save" x-show="old > price" @if ($d['old'] <= $d['price']) style="display:none" @endif>৳ <span x-text="old - price">{{ max(0, $d['old'] - $d['price']) }}</span> সাশ্রয়</span>
        </div>
        @if (filled(strip_tags((string) $p->short_description)))
            <p class="pp-short">{!! nl2br(e(trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li)[^>]*>#i', "\n", $p->short_description)), ENT_QUOTES, 'UTF-8')))) !!}</p>
        @endif
        <div class="pp-trust">
            <span class="pp-trust-stock"><span class="pp-dot {{ $d['inStock'] ? 'is-ok' : 'is-out' }}"></span>{{ $d['inStock'] ? 'স্টকে আছে' : 'স্টক শেষ' }}</span>
            <span>ক্যাশ অন ডেলিভারি</span>
            <span>২৪ ঘণ্টার মধ্যে এক্সচেঞ্জ</span>
        </div>
    </div>

    {{-- WhatsApp note (Settings → Website texts) --}}
    @if (($note['on'] ?? false) && ($note['text'] ?? ''))
        @php($wa = site()->whatsappLink(site()->text('content.product_note.whatsapp_text') ?: null))
        <a @if ($wa) href="{{ $wa }}" target="_blank" rel="noopener" @endif class="pp-wa">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="#25D366" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.1 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.4z"/></svg>
            <span>@if ($note['label'] ?? '')<b class="pp-red">{{ $note['label'] }}</b> @endif{!! str_replace('WhatsApp', '<b>WhatsApp</b>', e(site()->text('content.product_note.text'))) !!}</span>
        </a>
    @endif

    {{-- variation · qty · buttons --}}
    <div class="pp-card pp-stack-16">
        @if ($kind === 'variable')
            <div class="pp-stack-10">
                <div class="pp-row-between">
                    <span class="pp-label">সাইজ <span class="pp-muted" x-text="size ? '· ' + size : ''"></span></span>
                    @if ($d['chart']['head'] || $d['chart']['image'])<button type="button" class="pp-link" @click="chart = !chart" :aria-expanded="chart">সাইজ চার্ট</button>@endif
                </div>
                <div class="pp-chips" role="radiogroup" aria-label="সাইজ">
                    @foreach ($d['sizes'] as $s)
                        <button type="button" class="pp-chip" role="radio" @if ($s['out']) disabled @endif :class="size === @js($s['v']) && 'is-on'" :aria-checked="size === @js($s['v'])" @click="pickSize(@js($s['v']))">{{ $s['v'] }}</button>
                    @endforeach
                </div>
            </div>
        @elseif ($kind === 'combo')
            <div class="pp-stack-14">
                <div class="pp-row-between pp-wrap">
                    <span class="pp-label">প্রতিটি আইটেমের সাইজ বাছাই করুন</span>
                    @if ($d['chart']['head'] || $d['chart']['image'])<button type="button" class="pp-link" @click="chart = !chart" :aria-expanded="chart">সাইজ চার্ট</button>@endif
                </div>
                @if (collect($d['combo'])->whereNull('fixed')->count() > 1)
                    <label class="pp-check"><input type="checkbox" :checked="same" @change="toggleSame()"> সবগুলো একই সাইজ</label>
                @endif
                @foreach ($d['combo'] as $r)
                    <div class="pp-combo">
                        <div class="pp-combo-img" style="border-color: {{ $r['swatch'] }}">
                            @if ($r['image'])<img src="{{ $r['image'] }}" alt="{{ $r['name'] }}" loading="lazy" decoding="async" width="96" height="128">@endif
                            @if ($r['color'])<span class="pp-combo-color">{{ $r['color'] }}</span>@endif
                        </div>
                        <div class="pp-stack-8 pp-min0">
                            <div class="pp-combo-head">
                                <span class="pp-swatch" style="background: {{ $r['swatch'] }}"></span>
                                <span class="pp-pill">আইটেম {{ $bn($r['n']) }}</span>@if ($r['color'])<span class="pp-muted">{{ $r['color'] }}</span>@endif
                                @if ($r['qty'] > 1)<span class="pp-muted">× {{ $bn($r['qty']) }}</span>@endif
                                @unless ($r['fixed'])<span class="pp-combo-picked" x-text="combo[{{ $r['id'] }}] ? 'সাইজ ' + combo[{{ $r['id'] }}] : 'সাইজ বাছাই করুন'">সাইজ বাছাই করুন</span>@endunless
                            </div>
                            <span class="pp-combo-name">{{ $r['name'] }}</span>
                            @unless ($r['fixed'])
                                <div class="pp-chips pp-chips-sm" role="radiogroup" aria-label="আইটেম {{ $bn($r['n']) }} সাইজ">
                                    @foreach ($r['sizes'] as $s)
                                        <button type="button" class="pp-chip" role="radio" @if ($s['out']) disabled @endif :class="combo[{{ $r['id'] }}] === @js($s['v']) && 'is-on'" :aria-checked="combo[{{ $r['id'] }}] === @js($s['v'])" @click="pickCombo({{ $r['id'] }}, @js($s['v']))">{{ $s['v'] }}</button>
                                    @endforeach
                                </div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- size chart (Ops → product → Size chart) --}}
        @if ($kind !== 'simple' && ($d['chart']['head'] || $d['chart']['image']))
            <div x-show="chart" x-cloak x-collapse.duration.200ms class="pp-chart">
                @if ($d['chart']['head'])
                    <div class="pp-chart-scroll">
                        <table>
                            <thead><tr>@foreach ($d['chart']['head'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($d['chart']['rows'] as $row)<tr :class="picked(@js($row[0] ?? '')) && 'is-on'">@foreach ($row as $c)<td>{{ $c }}</td>@endforeach</tr>@endforeach</tbody>
                        </table>
                    </div>
                    <div class="pp-chart-note">সব মাপ ইঞ্চিতে</div>
                @else
                    <img src="{{ $d['chart']['image'] }}" alt="সাইজ চার্ট" loading="lazy" decoding="async" class="pp-chart-img">
                @endif
            </div>
        @endif

        <div class="pp-row-between pp-wrap">
            <span class="pp-label">{{ $kind === 'combo' ? 'কম্বো সেট' : 'পরিমাণ' }}</span>
            <div class="pp-qty">
                <button type="button" @click="dec()" :disabled="qty <= 1" aria-label="কমান">−</button>
                <span x-text="qty" aria-live="polite">1</span>
                <button type="button" @click="inc()" :disabled="qty >= max" aria-label="বাড়ান">+</button>
            </div>
        </div>

        <div @class(['pp-actions', 'pp-actions-one' => ! site()->cartEnabled()])>
            @if (site()->cartEnabled())
            <button type="button" class="pp-btn-outline" @click="addToCart()" :disabled="adding || !d.inStock" @if (! $d['inStock']) disabled @endif>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h11.5l2-8H6"/></svg>
                <span x-text="adding ? 'যোগ হচ্ছে…' : 'কার্টে যোগ করুন'">কার্টে যোগ করুন</span>
            </button>
            @endif
            <button type="button" class="pp-btn" @click="buyNow()" :disabled="!d.inStock" @if (! $d['inStock']) disabled @endif>{{ $d['inStock'] ? 'এখনই অর্ডার করুন' : 'স্টক শেষ' }}</button>
        </div>
        <div x-show="err" x-cloak class="pp-err" x-text="err" role="alert"></div>
    </div>

    {{-- order form (cash on delivery, no login) --}}
    @if ($d['inStock'])
    <div class="pp-card pp-stack-14" x-ref="form" id="order-form">
        <template x-if="!placed">
            <form class="pp-stack-14" @submit.prevent="placeOrder()" novalidate>
                <div class="pp-stack-2"><span class="pp-h2">অর্ডার ফর্ম</span><span class="pp-sub">পণ্য হাতে পেয়ে টাকা পরিশোধ করুন</span></div>
                <div class="pp-form-grid">
                    <label class="pp-field">আপনার নাম
                        <input x-model.trim="name" x-ref="name" name="name" autocomplete="name" placeholder="পুরো নাম লিখুন" maxlength="120" enterkeyhint="next" :class="bad === 'name' && 'is-bad'" @focus="beginCheckout()">
                    </label>
                    <label class="pp-field">মোবাইল নাম্বার
                        <input :value="phone" @input="setPhone($event)" x-ref="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel-national" placeholder="01XXXXXXXXX" maxlength="17" enterkeyhint="next" :class="bad === 'phone' && 'is-bad'" @focus="beginCheckout()">
                    </label>
                </div>
                <label class="pp-field">পূর্ণ ঠিকানা
                    <textarea x-model="address" x-ref="address" name="address" autocomplete="street-address" rows="2" maxlength="1000" placeholder="বাসা/রোড, এলাকা, থানা, জেলা" :class="bad === 'address' && 'is-bad'"></textarea>
                </label>
                <div class="pp-stack-8">
                    <span class="pp-label-sm">ডেলিভারি এরিয়া</span>
                    <div class="pp-areas" role="radiogroup" aria-label="ডেলিভারি এরিয়া">
                        @foreach ($d['areas'] as $a)
                            <button type="button" class="pp-area" role="radio" :class="area === {{ $a['id'] }} && 'is-on'" :aria-checked="area === {{ $a['id'] }}" @click="area = {{ $a['id'] }}">
                                <span class="pp-area-name"><span class="pp-radio"></span>{{ $a['name'] }}</span><b>৳{{ $a['fee'] }}</b>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="pp-summary">
                    <div><span class="pp-muted2" x-text="(d.kind === 'combo' ? 'কম্বো' : 'পণ্য') + ' × ' + qty">{{ $kind === 'combo' ? 'কম্বো' : 'পণ্য' }} × 1</span><span>৳ <span x-text="subtotal">{{ $d['price'] }}</span></span></div>
                    <div><span class="pp-muted2">ডেলিভারি চার্জ</span><span>৳ <span x-text="fee"></span></span></div>
                    <div class="pp-total"><span>মোট</span><span>৳ <span x-text="total"></span></span></div>
                </div>
                <button type="submit" class="pp-btn pp-btn-lg" :disabled="placing"><span x-text="placing ? 'অর্ডার হচ্ছে…' : 'অর্ডার কনফার্ম করুন · ৳ ' + total">অর্ডার কনফার্ম করুন</span></button>
                <div x-show="formErr" x-cloak class="pp-err" x-text="formErr" role="alert"></div>
            </form>
        </template>
        <template x-if="placed">
            <div class="pp-done" role="status">
                <span class="pp-done-check">✓</span>
                <span class="pp-h2">অর্ডার সম্পন্ন হয়েছে</span>
                <span class="pp-muted2">অর্ডার নম্বর <b x-text="'#' + placed.invoice"></b> · মোট ৳ <span x-text="placed.total"></span></span>
                <span class="pp-row-gap"><button type="button" class="pp-btn-ghost" @click="done = true">বিস্তারিত দেখুন</button><button type="button" class="pp-btn-ghost" @click="placed = null">আবার অর্ডার করুন</button></span>
            </div>
        </template>
    </div>

    {{-- order placed: modal (bottom sheet on phones) — everything comes with the placeOrder answer, nothing to load --}}
    <template x-teleport="body">
        <div class="pp-modal" x-show="done && placed" x-cloak x-transition.opacity.duration.150ms @keydown.escape.window="done = false" @click.self="done = false" role="dialog" aria-modal="true" aria-labelledby="pp-modal-title">
            <template x-if="placed">
                <div class="pp-sheet" x-trap.noscroll="done">
                    <button type="button" class="pp-sheet-x" @click="done = false" aria-label="বন্ধ করুন">×</button>
                    <div class="pp-sheet-head">
                        <span class="pp-done-check">✓</span>
                        <span class="pp-h2" id="pp-modal-title">অর্ডার সম্পন্ন হয়েছে</span>
                        <span class="pp-muted2">অর্ডার নম্বর <b x-text="'#' + placed.invoice"></b></span>
                        <span class="pp-muted2">আমাদের প্রতিনিধি শীঘ্রই <b x-text="placed.phone"></b> নাম্বারে কল করবেন।</span>
                    </div>
                    <div class="pp-sheet-box">
                        <template x-for="(it, i) in (placed.items || [])" :key="i">
                            <div class="pp-sheet-item">
                                <template x-if="it.image"><img :src="it.image" alt="" width="52" height="52" decoding="async"></template>
                                <div class="pp-sheet-item-txt"><b x-text="it.name"></b><span class="pp-muted2" x-text="[it.variant, 'পরিমাণ ' + it.qty].filter(Boolean).join(' · ')"></span></div>
                                <b class="pp-nowrap" x-text="'৳ ' + it.line"></b>
                            </div>
                        </template>
                        <div class="pp-sheet-sum">
                            <div><span class="pp-muted2">সাবটোটাল</span><span x-text="'৳ ' + placed.subtotal"></span></div>
                            <div><span class="pp-muted2">ডেলিভারি চার্জ</span><span x-text="'৳ ' + placed.delivery"></span></div>
                            <div class="pp-total"><span>মোট (ক্যাশ অন ডেলিভারি)</span><span x-text="'৳ ' + placed.total"></span></div>
                        </div>
                    </div>
                    <template x-if="placed.ship">
                        <div class="pp-sheet-box pp-sheet-ship">
                            <span class="pp-label-sm">ডেলিভারি তথ্য</span>
                            <div><span class="pp-muted2">নাম</span><b x-text="placed.ship.name"></b></div>
                            <div><span class="pp-muted2">মোবাইল</span><b x-text="placed.ship.phone"></b></div>
                            <div><span class="pp-muted2">ঠিকানা</span><b x-text="placed.ship.address"></b></div>
                            <div x-show="placed.ship.area"><span class="pp-muted2">এরিয়া</span><b x-text="placed.ship.area"></b></div>
                        </div>
                    </template>
                    <div class="pp-sheet-actions">
                        <a x-show="placed.whatsapp" :href="placed.whatsapp" target="_blank" rel="noopener" class="pp-btn pp-btn-wa">WhatsApp করুন</a>
                        <button type="button" class="pp-btn" @click="done = false">ঠিক আছে</button>
                    </div>
                </div>
            </template>
        </div>
    </template>
    @endif

    {{-- description --}}
    <div class="pp-card pp-card-text">
        <div class="pp-h3">পণ্যের বিবরণ</div>
        <div class="pp-stack-12 pp-mt12">
            @if ($kind === 'combo')
                <div class="pp-stack-6">
                    <span class="pp-label-sm pp-muted3">এই কম্বোতে যা থাকছে</span>
                    @foreach ($d['combo'] as $r)<div class="pp-combo-line"><span class="pp-swatch pp-swatch-sm" style="background: {{ $r['swatch'] }}"></span><span><b>{{ $r['name'] }}</b>@if ($r['color']) · {{ $r['color'] }}@endif @if ($r['qty'] > 1) × {{ $bn($r['qty']) }}@endif</span></div>@endforeach
                </div>
            @elseif ($kind === 'simple')
                <span class="pp-text">ফ্রি সাইজ / একটি ভেরিয়েশন — সাইজ বাছাই করার প্রয়োজন নেই।</span>
            @endif
            @if ($d['descriptionHtml'])
                <div class="pp-rich">{!! $d['descriptionHtml'] !!}</div>   {{-- cleaned by App\Support\RichText --}}
            @elseif ($d['features'])
                <ul class="pp-features">@foreach ($d['features'] as $f)<li><span class="pp-tick">✓</span><span>{{ $f }}</span></li>@endforeach</ul>
            @endif
        </div>
    </div>

    {{-- return & exchange policy (Settings → Website texts → FAQ lines) --}}
    @if ($policy = array_filter(site('content.faq', [])))
        <details class="pp-card pp-card-text">
            <summary class="pp-h3">রিটার্ন ও এক্সচেঞ্জ পলিসি</summary>
            <ul class="pp-policy">@foreach ($policy as $line)<li><span>»</span><span>{{ strtr($line, ['{store}' => site()->storeName(), '{whatsapp}' => site()->whatsapp()]) }}</span></li>@endforeach</ul>
        </details>
    @endif
</div>
