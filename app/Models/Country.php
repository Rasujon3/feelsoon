<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Country extends Model
    {
        protected $table = 'system_countries';

        protected $fillable = [
            "country_id",
            "country_code",
            "country_name",
            "phone_code",
            "country_vat",
            "default",
            "enabled",
            "country_order"
        ];
    }
