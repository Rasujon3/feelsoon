<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class ConversationMessage extends Model
    {
        protected $table = 'conversations_messages';

        public $timestamps = false;

        protected $fillable = [
            'message_id',
            'conversation_id',
            'user_id',
            'message',
            'image',
            'voice_note',
            'time'
        ];
    }
