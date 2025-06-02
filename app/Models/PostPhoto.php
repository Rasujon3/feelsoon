<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class PostPhoto extends Model
    {
        protected $table = 'posts_photos';

        protected $primaryKey = 'photo_id';

        public $timestamps = FALSE;

        protected $fillable = [
            'post_id',
            'album_id',
            'source',
            'blur',
            'pinned',
            'reaction_like_count',
            'reaction_love_count',
            'reaction_haha_count',
            'reaction_yay_count',
            'reaction_wow_count',
            'reaction_sad_count',
            'reaction_angry_count',
            'comments',
        ];
    }
