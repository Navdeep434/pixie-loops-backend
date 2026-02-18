<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomRequest extends Model
{
    protected $fillable = [
        'user_id', 'description', 'reference_image',
        'budget', 'status', 'admin_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}