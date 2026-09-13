<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Ajustes del sitio (settings públicos para el front + internos del panel). */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['group' => 'general', 'key' => 'site_name', 'value' => 'TornadoCMS', 'type' => 'text', 'is_public' => true, 'sort' => 10],
            ['group' => 'general', 'key' => 'site_tagline', 'value' => 'CMS a medida', 'type' => 'text', 'is_public' => true, 'sort' => 20],
            ['group' => 'contact', 'key' => 'contact_email', 'value' => 'hola@example.com', 'type' => 'text', 'is_public' => true, 'sort' => 30],
            ['group' => 'contact', 'key' => 'contact_phone', 'value' => '', 'type' => 'text', 'is_public' => true, 'sort' => 40],
            ['group' => 'shop', 'key' => 'shop_enabled', 'value' => true, 'type' => 'boolean', 'is_public' => true, 'sort' => 50],
            ['group' => 'shop', 'key' => 'shop_currency', 'value' => 'EUR', 'type' => 'text', 'is_public' => true, 'sort' => 60],
            ['group' => 'shop', 'key' => 'shipping_free_over_cents', 'value' => 5000, 'type' => 'number', 'is_public' => true, 'sort' => 70],
            ['group' => 'events', 'key' => 'events_booking_terms', 'value' => '', 'type' => 'json', 'is_public' => true, 'sort' => 80],
        ];

        foreach ($settings as $setting) {
            Setting::query()->firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
