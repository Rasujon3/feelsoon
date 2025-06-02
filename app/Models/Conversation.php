<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;

    class Conversation extends Model
    {
        protected $table = 'conversations';

        protected $primaryKey = 'conversation_id';

        public $timestamps = false;

        protected $fillable = [
            'conversation_id',
            'last_message_id',
            'color',
            'node_id',
            'node_type'
        ];
    }
