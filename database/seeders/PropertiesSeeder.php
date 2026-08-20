<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Property;

class PropertiesSeeder extends Seeder
{



    public function run()
    {
        // مصفوفة بيانات الشقق
        $properties = [
            [
                'user_id' => 1,
                'governorate_id' => 1,
                'city_id' => 1,
                'details' => 'شقة راقية في دمشق',
                'price' => 170000,
                'rate' => 4.7,
                'property_image' => 'properties/damascus2.jpg',
                'location'=>'Damascus',
                'latitude' => 33.5138,
                'longitude' => 36.2765
            ],
            [
                'user_id' => 2,
                'governorate_id' => 2,
                'city_id' => 6,
                'details' => 'شقة في داريا',
                'price' => 130000,
                'rate' => 4.1,
                'property_image' => 'properties/darya1.jpg',
                'location'=>'Damascus' ,
                'latitude' => 33.5138,
                'longitude' => 36.2765],
            [
                'user_id' => 3,
                'governorate_id' => 3,
                'city_id' => 10,
                'details' => 'شقة في منبج',
                'price' => 90000,
                'rate' => 3.9,
                'property_image' => 'properties/manbij1.jpg',
                'location'=>'Damascus' ,
                'latitude' => 33.5138,
                'longitude' => 36.2765       ],
        ];

        // استخدام foreach مع create عبر Model
        foreach ($properties as $index => $propertyData) {
            // يمكنك استخدام رقم الفهرس كـ key
            $propertyKey = "property_" . ($index + 1);

            // إنشاء الشقة باستخدام Model
            Property::create([
                'user_id' => $propertyData['user_id'],
                'governorate_id' => $propertyData['governorate_id'],
                'city_id' => $propertyData['city_id'],
                'details' => $propertyData['details'],
                'price' => $propertyData['price'],
                'rate' => $propertyData['rate'],
                'property_image' => $propertyData['property_image'],
                'location' => $propertyData['location'],
                'latitude' => $propertyData['latitude'],
                'longitude' => $propertyData['longitude'],
            ]);


        }
    }


}
