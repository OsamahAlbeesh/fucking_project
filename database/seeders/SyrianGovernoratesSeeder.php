<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyrianGovernoratesSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        DB::table('governorates')->insertOrIgnore([
            ['name' => 'دمشق', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ريف دمشق', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'حلب', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'حمص', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'حماة', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'اللاذقية', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'طرطوس', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'إدلب', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'دير الزور', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'الحسكة', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'الرقة', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'السويداء', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'درعا', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'القنيطرة', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
