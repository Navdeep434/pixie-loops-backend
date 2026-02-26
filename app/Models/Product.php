<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'sale_price',
        'stock',
        'sku',
        'status',
    ];

    protected $casts = [
        'price'      => 'float',
        'sale_price' => 'float',
        'stock'      => 'integer',
        'status'     => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class)
                    ->whereNull('deleted_at');
    }

    public function getFinalPriceAttribute(): float
    {
        return $this->sale_price ?? $this->price;
    }
}
