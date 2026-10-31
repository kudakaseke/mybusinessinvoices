<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentDraft extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'key', 'kind', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
