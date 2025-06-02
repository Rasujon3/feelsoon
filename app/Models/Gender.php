<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Gender extends Model
    {
        protected $table = 'system_genders';

        protected $fillable = [
            "gender_id",
            "gender_name",
            'gender_order'
        ];
    }
