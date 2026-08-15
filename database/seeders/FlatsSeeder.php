<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Flat;

class FlatsSeeder extends Seeder
{



    public function run()
    {
        // مصفوفة بيانات الشقق
        $flats = [
            [
                'user_id' => 1,
                'governorate_id' => 1,
                'city_id' => 1,
                'details' => 'شقة راقية في دمشق',
                'price' => 170000,
                'rate' => 4.7,
                'flat_image' => 'flats/damascus2.jpg',
                'location' => 'DX123Cdk345AQx'
            ],
            [
                'user_id' => 2,
                'governorate_id' => 1,
                'city_id' => 3,
                'details' => 'شقة في داريا',
                'price' => 130000,
                'rate' => 4.1,
                'flat_image' => 'flats/darya1.jpg',
                'location' => 'DX12fk2l34jx'
            ],
            [
                'user_id' => 3,
                'governorate_id' => 3,
                'city_id' => 7,
                'details' => 'شقة في منبج',
                'price' => 90000,
                'rate' => 3.9,
                'flat_image' => 'flats/manbij1.jpg',
                'location' => 'kdf903jcn82kzio'
            ],
        ];

        // استخدام foreach مع create عبر Model
        foreach ($flats as $index => $flatData) {
            // يمكنك استخدام رقم الفهرس كـ key
            $flatKey = "flat_" . ($index + 1);

            // إنشاء الشقة باستخدام Model
            Flat::create([
                'user_id' => $flatData['user_id'],
                'governorate_id' => $flatData['governorate_id'],
                'city_id' => $flatData['city_id'],
                'details' => $flatData['details'],
                'price' => $flatData['price'],
                'rate' => $flatData['rate'],
                'flat_image' => $flatData['flat_image'],
                'location' => $flatData['location'],
            ]);


        }
    }


}
