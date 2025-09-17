<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cargo_no',
        'cargo_type',
        'cargo_size',
        'weight',
        'remarks',
        'wharfage',
        'penalty_days',
        'storage',
        'electricity',
        'destuffing',
        'lifting',
    ];
}
