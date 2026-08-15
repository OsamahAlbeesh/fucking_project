<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $guarded=[];
    public function flats()
    {
        return $this-> hasMany(Flat::class);
    }
    public function governorate()
    {
        return $this-> belongsTo(Governorate::class);
    }
}
