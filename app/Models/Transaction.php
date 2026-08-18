<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'property_id',
        'property_user_id',
        'payment_method',
        'stripe_payment_id',
        'amount',
        'commission',
        'type',
        'status',
        'contract_pdf',
        'stripe_session_id',
        'payment_details',
    ];
    protected function amount(): \Illuminate\Database\Eloquent\Casts\Attribute{
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => $value / 100, // للعرض بالدولار/الليرة
            set: fn ($value) => $value * 100, // للتخزين كـ Integer متوافق مع Stripe
        );
    }

    public function user() {
    return $this->belongsTo(User::class, 'user_id');
    }

    public function property(){
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function booking(){
        return $this->belongsTo(PropertyUser::class, 'property_user_id');
    }

}
