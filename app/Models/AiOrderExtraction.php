<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiOrderExtraction extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'source',
        'input_type',
        'raw_text_input',
        'image_paths',
        'extracted_json',
        'confidence',
        'warnings',
        'resolved_json',
        'status',
        'order_id',
    ];

    protected $casts = [
        'image_paths' => 'array',
        'extracted_json' => 'array',
        'warnings' => 'array',
        'resolved_json' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
