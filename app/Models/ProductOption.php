<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOption extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'is_required',
        'min_value',
        'max_value',
        'price_per_unit',
    ];

    protected $casts = [
        'is_required'    => 'boolean',
        'min_value'      => 'integer',
        'max_value'      => 'integer',
        'price_per_unit' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function values()
    {
        return $this->hasMany(ProductOptionValue::class)
                    ->whereNull('deleted_at');
    }
}