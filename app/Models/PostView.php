<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class PostView extends Model
    {
        protected $table = 'posts_views';

        public $timestamps = FALSE;

        protected $fillable = [
            'post_id',
            'user_id',
            'view_date'
        ];
    }
