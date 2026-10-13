<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return Setting::orderBy('group')->orderBy('key')->get();
    }

    public function show(string $key)
    {
        return Setting::where('key', $key)->firstOrFail();
    }

    public function update(Request $request, string $key)
    {
        $data = $request->validate(['value' => 'present', 'group' => 'nullable|string|max:50']);
        $setting = Setting::updateOrCreate(['key' => $key], ['value' => $data['value'], 'group' => $data['group'] ?? 'general']);

        return $setting;
    }

    public function bulk(Request $request)
    {
        $data = $request->validate(['settings' => 'required|array']);
        foreach ($data['settings'] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json(['message' => 'Settings updated.']);
    }
}
