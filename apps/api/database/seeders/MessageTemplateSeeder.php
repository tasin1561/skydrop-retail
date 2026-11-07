<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use App\Services\Messaging\Events;
use Illuminate\Database\Seeder;

/** Seeds the default en + bn templates for every event. Existing rows (admin edits) are left untouched. */
class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Events::defaultTemplates() as $row) {
            MessageTemplate::query()->firstOrCreate(['key' => $row['key'], 'channel' => $row['channel'], 'locale' => $row['locale']], $row);
        }
    }
}
