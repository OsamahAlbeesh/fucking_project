<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $guarded=[];
    public function property()
    {
        return $this-> hasMany(Property::class);
    }
    public function governorate()
    {
        return $this-> belongsTo(Governorate::class);
    }
}
