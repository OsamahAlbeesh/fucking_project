<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlatUser extends Model
{
    protected $table = 'flat_user';
    protected $guarded = [];

    public function flat() {
        return $this->belongsTo(Flat::class, 'flat_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payment() {
        return $this->hasOne(Transaction::class, 'flat_user_id');
    }
}
