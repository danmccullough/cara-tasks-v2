<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chore extends Model
{
    /** @use HasFactory<\Database\Factories\ChoreFactory> */
    use HasFactory;

    protected $fillable = ['title', 'icon', 'instructions', 'points', 'active'];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
