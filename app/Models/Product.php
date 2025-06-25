<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $keyType = 'int';
    protected $primaryKey = 'pid';
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'image',
    ];


    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
