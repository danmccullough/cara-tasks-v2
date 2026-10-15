<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    /** @use HasFactory<\Database\Factories\AssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'chore_id',
        'kid_id',
        'days_of_week',
        'due_time',
        'remind_before_min',
        'nag_every_min',
        'max_nags',
        'start_date',
        'end_date',
        'active',
    ];

    // Mirror the column defaults so a freshly created model has them before a reload.
    protected $attributes = [
        'remind_before_min' => 15,
        'nag_every_min' => 10,
        'max_nags' => 3,
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'active' => 'boolean',
            'remind_before_min' => 'integer',
            'nag_every_min' => 'integer',
            'max_nags' => 'integer',
        ];
    }

    public function chore(): BelongsTo
    {
        return $this->belongsTo(Chore::class);
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kid_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class);
    }
}
