<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class PostVideo extends Model
    {
        protected $table = 'posts_videos';

        protected $primaryKey = 'video_id';

        public $timestamps = FALSE;

        protected $fillable = [
            'post_id',
            'category_id',
            'source',
            'source_240p',
            'source_360p',
            'source_480p',
            'source_720p',
            'source_1080p',
            'source_1440p',
            'source_2160p',
            'thumbnail',
            'views'
        ];
    }
