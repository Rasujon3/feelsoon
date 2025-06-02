<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class UserInterest extends Model
    {
        protected $table = 'users_interests';

        protected $fillable = ['user_id', 'blocked_user_id'];

        public $timestamps = false;
    }
