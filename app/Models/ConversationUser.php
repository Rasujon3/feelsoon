<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class ConversationUser extends Model
    {
        protected $table = 'conversations_users';

        public $timestamps = false;

        protected $fillable = [
            'conversation_id',
            'user_id',
            'seen',
            'typing',
            'deleted'
        ];

        public function users()
        {
            return $this->belongsToMany(User::class, 'conversations_users', 'conversation_id', 'user_id');
        }
    }
