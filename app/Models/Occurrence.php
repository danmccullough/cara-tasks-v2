<?php

namespace App\Models;

use App\Enums\OccurrenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Occurrence extends Model
{
    /** @use HasFactory<\Database\Factories\OccurrenceFactory> */
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'chore_id',
        'kid_id',
        'due_at',
        'status',
        'reminders_sent',
        'last_reminded_at',
        'parents_alerted_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $attributes = [
        'status' => 'pending',
        'reminders_sent' => 0,
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'parents_alerted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'status' => OccurrenceStatus::class,
            'reminders_sent' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function chore(): BelongsTo
    {
        return $this->belongsTo(Chore::class);
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kid_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
