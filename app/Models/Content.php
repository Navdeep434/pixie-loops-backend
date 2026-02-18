<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $fillable = ['key', 'title', 'body', 'image', 'status'];

    protected $casts = ['status' => 'boolean'];
}