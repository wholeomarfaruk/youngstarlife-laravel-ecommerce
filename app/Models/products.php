<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class products extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'status',
    ];
    protected $appends = ['featured_image','sizechart'];
    use HasFactory;
    public function orderItems()
    {
        return $this->hasMany(Order_Item::class);
    }
    public function media()
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    public function getFeaturedImageAttribute()
    {
        $media = $this->media?->where('category', 'featured_image')->first();

        if ($media && file_exists(public_path('uploads/' . $media->path))) {
            return asset('uploads/' . $media->path);
        }

        return asset('website/img/thumbnails/featured_img.jpg');
    }
    public function getSizechartAttribute()
    {
        $media = $this->media?->where('category', 'sizechart')->first();

        if ($media && file_exists(public_path($media->path))) {
            return asset($media->path);
        }

        return null;
    }
    public function sizes()
    {
        return $this->hasMany(Size::class);
    }
       public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }
}
