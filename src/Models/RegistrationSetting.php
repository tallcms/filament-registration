<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationSetting extends Model
{
    protected $table = 'tallcms_registration_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];
}
