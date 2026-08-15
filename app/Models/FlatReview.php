<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlatReview extends Model
{
    protected $fillable = ['flat_id', 'user_id', 'rating', 'review'];

    public function flat()
    {
        return $this->belongsTo(Flat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
