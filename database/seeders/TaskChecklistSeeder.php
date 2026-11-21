<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TaskType;
use App\Models\TaskChecklistTemplate;
use Illuminate\Database\Seeder;

/**
 * Checklists confirmed in SPEC v5.3 (HK-04). Other task types start without a checklist until the
 * lodge supplies one (Settings › Task checklists). Never overwrites edits.
 */
class TaskChecklistSeeder extends Seeder
{
    public function run(): void
    {
        TaskChecklistTemplate::query()->firstOrCreate(['task_type' => TaskType::DeepClean], ['items' => [
            'Under beds',
            'Floors',
            'Shower',
            'Beds',
            'Aluminium windows and doors',
            'Mirrors',
            'Microwave',
            'Kettle',
            'Drinking water',
            'Toiletries',
            'Towels',
            'Coffee and sugar',
        ]]);
    }
}
