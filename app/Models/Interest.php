<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Interest extends Model
    {
        protected $table = 'pages_categories';

        protected $fillable = [
            "category_id",
            "category_parent_id",
            "category_name",
            "category_description",
            "category_order"
        ];
    }
