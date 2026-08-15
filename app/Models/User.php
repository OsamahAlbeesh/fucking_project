<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Flat;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;


    protected $guarded = [
    ];


    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }


    public function favorites(){
        return $this->belongsToMany(Flat::class, 'favorites')
            ->withTimestamps()
            ->withPivot('id');
    }


    public function flats(){
        return $this->hasMany(Flat::class);
    }


    // public function flatOrders(){
    //     return $this->belongsToMany(Flat::class, 'flat_user')
    //         ->withPivot('start_date', 'end_date', 'status', 'rate')
    //         ->withTimestamps();
    // }

    public function transactions() {
        return $this->hasMany(Transaction::class);
    }

    public function bookings(){
        return $this->hasMany(FlatUser::class);
    }
}
