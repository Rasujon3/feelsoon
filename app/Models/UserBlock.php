<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class UserBlock extends Model
    {
        protected $table = 'users_blocks';

        protected $fillable = [
            'user_id',
            'blocked_id'
        ];

        public $timestamps = false;
    }
