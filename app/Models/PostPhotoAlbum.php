<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class PostPhotoAlbum extends Model
    {
        protected $table = 'posts_photos_albums';

        protected $primaryKey = 'album_id';

        public $timestamps = FALSE;

        protected $fillable = [
            'user_id',
            'user_type',
            'in_group',
            'group_id',
            'in_event',
            'event_id',
            'title',
            'privacy'
        ];
    }
