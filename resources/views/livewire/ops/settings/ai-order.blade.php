<div class="card">
    <div class="card-head"><span class="card-title">AI order entry</span><a href="{{ route('ops.orders.bulk', ['intake' => 1]) }}" wire:navigate class="text-sm">Open Bulk order · AI Order →</a></div>
    <form wire:submit="save" class="flex flex-col gap-4 p-3.5">
        @if ($errors->any())<div class="rounded-md bg-bad-bg px-3 py-2 text-sm text-bad">{{ $errors->first() }}</div>@endif
        <p class="text-sm text-ink-muted">The text parser always runs first (free). The AI (via <a href="https://openrouter.ai" target="_blank">OpenRouter</a>) is only called for screenshots / PDFs or when something the parser couldn't settle is in the message. Product ids, prices and totals always come from your catalogue.</p>

        <label class="flex items-center gap-2 font-medium"><input type="checkbox" wire:model="s.enabled" class="h-4 w-4 accent-brand"> Use AI when the parser needs help</label>

        <div class="rounded-lg border border-line p-3">
            <div class="mb-2 text-sm font-semibold">OpenRouter API key</div>
            <p class="mb-2 text-sm">
                @if ($this->key['source'] === 'settings')Saved here: <span class="mono">{{ $this->key['masked'] }}</span> <button type="button" wire:click="removeKey" wire:confirm="Remove the saved key?" class="ml-2 text-bad">Remove</button>
                @elseif ($this->key['source'] === 'env')Using <span class="mono">OPENROUTER_API_KEY</span> from .env (<span class="mono">{{ $this->key['masked'] }}</span>) — a key saved here takes over.
                @else<span class="text-warn">No key yet — the AI can't run.</span>@endif
            </p>
            <x-ops.field label="New key" hint="Stored encrypted; never shown again or sent to the browser." :error="$errors->first('newKey')"><input type="password" wire:model="newKey" class="input mono" autocomplete="off" placeholder="sk-or-v1-…"></x-ops.field>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-ops.field label="Model" hint="e.g. google/gemini-2.5-flash, openai/gpt-4o-mini, openrouter/free" :error="$errors->first('s.model')"><input wire:model="s.model" class="input mono"></x-ops.field>
            <x-ops.field label="Fallback model" hint="Tried when the first fails (optional)"><input wire:model="s.fallback_model" class="input mono"></x-ops.field>
            <x-ops.field label="Temperature" hint="0 = most exact"><input type="number" step="0.1" min="0" max="1" wire:model="s.temperature" class="input"></x-ops.field>
            <x-ops.field label="Timeout (seconds)"><input type="number" min="5" max="120" wire:model="s.timeout" class="input"></x-ops.field>
            <x-ops.field label="Max output tokens"><input type="number" min="500" max="16000" wire:model="s.max_tokens" class="input"></x-ops.field>
            <x-ops.field label="Products sent to the AI" hint="Closest catalogue matches included as context"><input type="number" min="10" max="200" wire:model="s.max_candidates" class="input"></x-ops.field>
            <x-ops.field label="AI calls per day" hint="0 = no limit"><input type="number" min="0" wire:model="s.daily_limit" class="input"></x-ops.field>
            <x-ops.field label="AI calls per minute (per user)"><input type="number" min="1" wire:model="s.per_minute" class="input"></x-ops.field>
            <x-ops.field label="Files per extraction"><input type="number" min="1" max="10" wire:model="s.max_files" class="input"></x-ops.field>
            <x-ops.field label="Max file size (KB)"><input type="number" min="100" max="20480" wire:model="s.max_file_kb" class="input"></x-ops.field>
            <x-ops.field label="Extra instructions for the AI" class="sm:col-span-2" hint="Shop-specific hints, e.g. “‘Cargo’ means Cargo Pant”"><textarea wire:model="s.extra_prompt" rows="3" class="input"></textarea></x-ops.field>
        </div>
        <button class="btn-primary self-start">Save AI settings</button>
    </form>

    @php($u = $this->usage)
    <div class="border-t border-soft p-3.5">
        <div class="mb-2 text-sm font-semibold">Last 30 days</div>
        <x-ops.kpis>
            <x-ops.kpi label="Extractions" :value="$u['extractions']" /><x-ops.kpi label="Parser only (free)" :value="$u['parser_only']" tone="ok" />
            <x-ops.kpi label="AI calls" :value="$u['ai_calls']" hint="today {{ $u['ai_today'] }}" /><x-ops.kpi label="AI failed" :value="$u['ai_failed']" :tone="$u['ai_failed'] ? 'bad' : 'ink'" />
            <x-ops.kpi label="Tokens" :value="ops_num($u['tokens'])" /><x-ops.kpi label="Cost (USD)" :value="'$'.number_format($u['cost'], 4)" />
            <x-ops.kpi label="Avg AI time" :value="round($u['avg_latency'] / 1000, 1).'s'" /><x-ops.kpi label="Orders created" :value="$u['orders_placed']" tone="ok" />
        </x-ops.kpis>
    </div>
</div>
