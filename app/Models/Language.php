<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $table = 'system_languages';

    protected $fillable = [
        "language_id",
        "code",
        "title",
        "flag",
        "dir",
        "default",
        "enabled",
        "language_order"
    ];
}
