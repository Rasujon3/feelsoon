<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class PostShare extends Model
    {
        protected $table = 'posts_shares';

        public $timestamps = FALSE;

        protected $fillable = [
            'post_id',
            'user_id',
            'share_time'
        ];
    }
