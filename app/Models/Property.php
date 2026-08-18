<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model{

    protected $guarded=[];

    protected $table = 'properties';

    public function city(){
        return $this-> belongsTo(City::class);
    }
    public function governorate(){
        return $this-> belongsTo(Governorate::class);
    }
    public function owner(){
        return $this-> belongsTo(User::class, 'user_id');
    }

    public function favorite(){
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps()
            ->withPivot('id');
    }

    public function renters(){
        return $this->belongsToMany(User::class, 'property_user')
              ->withPivot('start_date', 'end_date', 'status', 'rate')
              ->withTimestamps();
    }
    public function reviews(){
        return $this->hasMany(PropertyReview::class);
    }

    public function bookings() {
        return $this->hasMany(PropertyUser::class, 'property_id');
    }

    public function transactions() {
        return $this->hasMany(Transaction::class, 'property_id');
    }
}

