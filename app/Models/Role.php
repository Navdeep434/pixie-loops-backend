<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role')->withTimestamps();
    }

    public function givePermissionTo(string $permission): void
    {
        $permissionModel = Permission::where('slug', $permission)->firstOrFail();
        $this->permissions()->syncWithoutDetaching([$permissionModel->id]);
    }
}
