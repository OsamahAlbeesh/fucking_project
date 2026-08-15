<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyrianCitiesSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        // First, get governorate IDs
        $governorates = DB::table('governorates')->pluck('id', 'name');

        DB::table('cities')->insert([
            // Damascus Governorate (دمشق)
            ['name' => 'كفرسوسة', 'governorate_id' => $governorates['دمشق'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'مزة', 'governorate_id' => $governorates['دمشق'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'برامكة', 'governorate_id' => $governorates['دمشق'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'المالكي', 'governorate_id' => $governorates['دمشق'], 'created_at' => $now, 'updated_at' => $now],

            // Rif Dimashq Governorate (ريف دمشق)
            ['name' => 'دوما', 'governorate_id' => $governorates['ريف دمشق'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'داريا', 'governorate_id' => $governorates['ريف دمشق'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'الزبداني', 'governorate_id' => $governorates['ريف دمشق'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'جرمانا', 'governorate_id' => $governorates['ريف دمشق'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'صحنايا', 'governorate_id' => $governorates['ريف دمشق'], 'created_at' => $now, 'updated_at' => $now],

            // Aleppo Governorate (حلب)
            ['name' => 'منبج', 'governorate_id' => $governorates['حلب'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'السفيرة', 'governorate_id' => $governorates['حلب'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'الباب', 'governorate_id' => $governorates['حلب'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'عفرين', 'governorate_id' => $governorates['حلب'], 'created_at' => $now, 'updated_at' => $now],

            // Homs Governorate (حمص)
            ['name' => 'النبك', 'governorate_id' => $governorates['حمص'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'تدمر', 'governorate_id' => $governorates['حمص'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'الرستن', 'governorate_id' => $governorates['حمص'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'القريتين', 'governorate_id' => $governorates['حمص'], 'created_at' => $now, 'updated_at' => $now],

            // Hama Governorate (حماة)
            ['name' => 'السقيلبية', 'governorate_id' => $governorates['حماة'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'مصياف', 'governorate_id' => $governorates['حماة'], 'created_at' => $now, 'updated_at' => $now],

            // Latakia Governorate (اللاذقية)
            ['name' => 'كسب', 'governorate_id' => $governorates['اللاذقية'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'جبلة', 'governorate_id' => $governorates['اللاذقية'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'القرداحة', 'governorate_id' => $governorates['اللاذقية'], 'created_at' => $now, 'updated_at' => $now],

            // Tartus Governorate (طرطوس)
            ['name' => 'بانياس', 'governorate_id' => $governorates['طرطوس'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'صافيتا', 'governorate_id' => $governorates['طرطوس'], 'created_at' => $now, 'updated_at' => $now],

            // Idlib Governorate (إدلب)
            ['name' => 'سرمدا', 'governorate_id' => $governorates['إدلب'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'حارم', 'governorate_id' => $governorates['إدلب'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'أريحا', 'governorate_id' => $governorates['إدلب'], 'created_at' => $now, 'updated_at' => $now],

            // Deir ez-Zor Governorate (دير الزور)
            ['name' => 'البوكمال', 'governorate_id' => $governorates['دير الزور'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'الميادين', 'governorate_id' => $governorates['دير الزور'], 'created_at' => $now, 'updated_at' => $now],

            // Al-Hasakah Governorate (الحسكة)
            ['name' => 'القامشلي', 'governorate_id' => $governorates['الحسكة'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'رأس العين', 'governorate_id' => $governorates['الحسكة'], 'created_at' => $now, 'updated_at' => $now],

            // Raqqa Governorate (الرقة)
            ['name' => 'الثورة', 'governorate_id' => $governorates['الرقة'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'تلبيسة', 'governorate_id' => $governorates['الرقة'], 'created_at' => $now, 'updated_at' => $now],

            // As-Suwayda Governorate (السويداء)
            ['name' => 'الخربة', 'governorate_id' => $governorates['السويداء'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'شهبا', 'governorate_id' => $governorates['السويداء'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'صلخد', 'governorate_id' => $governorates['السويداء'], 'created_at' => $now, 'updated_at' => $now],

            // Daraa Governorate (درعا)
            ['name' => 'حوران', 'governorate_id' => $governorates['درعا'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ازرع', 'governorate_id' => $governorates['درعا'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'صنعاء', 'governorate_id' => $governorates['درعا'], 'created_at' => $now, 'updated_at' => $now],

            // Quneitra Governorate (القنيطرة)
            ['name' => 'قطنا', 'governorate_id' => $governorates['القنيطرة'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'فيق', 'governorate_id' => $governorates['القنيطرة'], 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
