<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Setting::query()->orderBy('group')->orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', Rule::exists(Setting::class, 'key')],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($data['settings'] as $entry) {
            /** @var Setting|null $setting */
            $setting = Setting::query()->where('key', $entry['key'])->first();
            $setting?->update(['value' => $this->cast($entry['value'], $setting->type)]);
        }

        return response()->json([
            'data' => Setting::query()->orderBy('group')->orderBy('sort')->get(),
            'message' => 'Ajustes guardados.',
        ]);
    }

    protected function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : null,
            default => $value,
        };
    }
}
