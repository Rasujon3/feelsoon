<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;

    class Following extends Model
    {
        protected $fillable = [
            'user_id',
            'following_id',
            'points_earned',
            'status',
            'time'
        ];

        public $timestamps = false;

        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class, 'user_id', 'user_id');
        }
    }
