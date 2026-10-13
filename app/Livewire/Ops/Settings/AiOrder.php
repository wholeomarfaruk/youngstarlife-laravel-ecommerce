<?php

namespace App\Livewire\Ops\Settings;

use App\OrderIntake\{IntakeSettings, OrderIntakeService};
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Ops → Settings → AI order entry: OpenRouter key (stored encrypted), models, limits, and 30-day usage. */
class AiOrder extends Component
{
    public array $s = [];
    public string $newKey = '';

    protected const KEYS = ['enabled', 'model', 'fallback_model', 'temperature', 'timeout', 'max_tokens', 'daily_limit', 'per_minute', 'max_files', 'max_file_kb', 'max_candidates', 'extra_prompt'];

    public function mount(): void
    {
        $c = app(IntakeSettings::class);
        $this->s = ['enabled' => $c->enabled(), 'model' => $c->model(), 'fallback_model' => $c->fallbackModel(), 'temperature' => $c->temperature(), 'timeout' => $c->timeout(),
            'max_tokens' => $c->maxOutputTokens(), 'daily_limit' => $c->dailyLimit(), 'per_minute' => $c->perMinute(), 'max_files' => $c->maxFiles(),
            'max_file_kb' => $c->maxFileKb(), 'max_candidates' => $c->maxCandidates(), 'extra_prompt' => $c->extraPrompt()];
    }

    #[Computed] public function key(): array { $c = app(IntakeSettings::class); return ['masked' => $c->maskedKey(), 'source' => $c->keySource()]; }
    #[Computed] public function usage(): array { return OrderIntakeService::usageStats(); }

    public function save(): void
    {
        $this->validate([
            's.model' => 'required|max:120', 's.fallback_model' => 'nullable|max:120', 's.temperature' => 'numeric|min:0|max:1', 's.timeout' => 'integer|min:5|max:120',
            's.max_tokens' => 'integer|min:500|max:16000', 's.daily_limit' => 'integer|min:0|max:100000', 's.per_minute' => 'integer|min:1|max:120',
            's.max_files' => 'integer|min:1|max:10', 's.max_file_kb' => 'integer|min:100|max:20480', 's.max_candidates' => 'integer|min:10|max:200', 's.extra_prompt' => 'nullable|max:2000',
            'newKey' => 'nullable|string|min:20|max:300',
        ]);
        foreach (self::KEYS as $k) IntakeSettings::set($k, $k === 'enabled' ? ($this->s[$k] ? '1' : '0') : (string) $this->s[$k]);
        if (trim($this->newKey) !== '') { IntakeSettings::storeApiKey(trim($this->newKey)); $this->newKey = ''; }
        unset($this->key);
        ops_log('AI order entry settings saved');
        $this->dispatch('toast', message: 'AI settings saved');
    }

    public function removeKey(): void
    {
        IntakeSettings::storeApiKey(null); unset($this->key);
        ops_log('AI order entry API key removed');
        $this->dispatch('toast', message: 'Saved key removed'.(config('services.openrouter.api_key') ? ' — the .env key is used now' : ''));
    }

    public function render() { return view('livewire.ops.settings.ai-order'); }
}
