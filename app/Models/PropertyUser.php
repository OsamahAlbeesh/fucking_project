<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyUser extends Model
{
    protected $table = 'property_user';
    protected $guarded = [];

    public function property() {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payment() {
        return $this->hasOne(Transaction::class, 'property_user_id');
    }
}
