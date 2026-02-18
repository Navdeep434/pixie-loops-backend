<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    // GET /admin/settings
    public function index()
    {
        $settings = Setting::all()->mapWithKeys(fn($s) => [$s->key => $s->value]);

        return response()->json([
            'success'  => true,
            'settings' => $settings,
        ]);
    }

    // PUT /admin/settings  ← bulk update
    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'nullable|string',
        ]);

        foreach ($validated['settings'] as $key => $value) {
            Setting::updateOrCreate(
                ['key'   => $key],
                ['value' => $value]
            );
        }

        $settings = Setting::all()->mapWithKeys(fn($s) => [$s->key => $s->value]);

        return response()->json([
            'success'  => true,
            'message'  => 'Settings updated successfully',
            'settings' => $settings,
        ]);
    }
}