<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {

        User::create([
            'phone' => '0900000000',
            'password'=>bcrypt('password'),
            'first_name'=>'Admin',
            'last_name'=>'admin',
            'photo_url'=>'storage/photos/random.jpg',
            'id_photo' => 'storage/photos/random.jpg',
            'role'=>'admin',
            'birth_date'=>'2002-08-01',
            'verified_status' => 'approved',
            'balance'=>'192324222',
        ]);
        User::factory(10)->create();
    }
}
