<?php

namespace App\Models\Concerns;

/**
 * MySQL runs in strict mode, so a value longer than its VARCHAR column fails the whole insert
 * ("Data too long"). Customer input (a pasted name, a long reason, AI output) must never cost an
 * order, so values are cut to the column size when they are set on the model.
 *
 * Usage: protected array $stringLimits = ['name' => 255];
 */
trait LimitsStringLength
{
    public function setAttribute($key, $value)
    {
        $limit = $this->stringLimits[$key] ?? null;
        if ($limit && is_string($value) && mb_strlen($value) > $limit) {
            $value = mb_substr($value, 0, $limit);
        }

        return parent::setAttribute($key, $value);
    }
}
