<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'action', 'record_type', 'record_id', 'description', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }
}
