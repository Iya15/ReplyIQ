<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Membership extends Pivot
{
    use HasFactory, HasUuids;

    protected $table = 'memberships';

    // Pivot models disable incrementing by default, but HasUuids also sets
    // $incrementing = false and $keyType = 'string' — both are consistent here.
    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
    ];
}
