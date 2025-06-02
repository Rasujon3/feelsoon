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
            'file_path'
        ];

        // public function user()
        // {
        //     return $this->belongsTo(related: User::class);
        // }

    }
