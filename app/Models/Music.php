<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Facades\DB;

    class Music extends Model
    {
        protected $table = 'musics';
        protected $primaryKey = 'id';

        public $timestamps = FALSE;

        protected $fillable = [
            'user_id',
            'title',
            'singer_name',
            'file_path',
            'created_at',
            'updated_at'
        ];
         public function posts()
         {
             return $this->hasMany(Post::class, 'music_id', 'id');
         }

    }
