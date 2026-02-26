<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOptionValue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_option_id',
        'label',
        'value',
        'price_modifier',
    ];

    protected $casts = [
        'price_modifier' => 'float',
    ];

    public function option()
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}