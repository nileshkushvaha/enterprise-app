<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'gender',
        'date_of_birth',
        'avatar',
        'address',
        'city',
        'state',
        'country_id',
        'postal_code',
        'timezone',
        'language',
        'date_format',
        'time_format',
        'theme',
        'notification_preferences',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth'              => 'date',
            'notification_preferences'   => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
