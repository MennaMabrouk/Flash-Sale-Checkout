<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Order extends Model
{
    protected $fillable = ['hold_id', 'status'];

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    public function hold()
    {
        return $this->belongsTo(Hold::class);
    }

    use HasFactory;
}
