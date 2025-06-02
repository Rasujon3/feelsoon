<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;

    class Notification extends Model
    {
        protected $primaryKey = 'notification_id';

        public $timestamps = FALSE;
        protected $fillable = [
            'to_user_id',
            'from_user_id',
            'from_user_type',
            'action',
            'node_type',
            'node_id',
            'node_url',
            'notify_id',
            'message',
            'time',
            'seen'
        ];

        public function fromUser(): BelongsTo
        {
            return $this->belongsTo(User::class, 'from_user_id', 'user_id');
        }

        public function toUser(): BelongsTo
        {
            return $this->belongsTo(User::class, 'to_user_id', 'user_id');
        }

        public function post()
        {
            if($this->attributes['node_type'] == 'post') {
                return $this->belongsTo(Post::class, 'node_url', 'post_id');
            }
            return $this->belongsTo(Post::class, 0, 'post_id');
        }
    }
