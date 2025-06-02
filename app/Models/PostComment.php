<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Database\Eloquent\Relations\BelongsTo;
    use Illuminate\Database\Eloquent\Relations\HasMany;
    use Illuminate\Support\Facades\DB;

    class PostComment extends Model
    {
        protected $table = 'posts_comments';

        public $timestamps = FALSE;

        protected $primaryKey = 'comment_id';

        protected $fillable = [
            'comment_id',
            'node_id',
            'node_type',
            'user_id',
            'user_type',
            'text',
            'image',
            'voice_note',
            'time',
            'reaction_like_count',
            'reaction_love_count',
            'reaction_haha_count',
            'reaction_yay_count',
            'reaction_wow_count',
            'reaction_sad_count',
            'reaction_angry_count',
            'replies',
            'points_earned'
        ];

        protected $appends = [
            'is_like'
        ];

        public function getIsLikeAttribute()
        {
            return DB::table('posts_comments_reactions')->where('user_id', request()->user()->user_id ?? null)->where('comment_id', $this->attributes['comment_id'])->count();
        }

        public function commentReplies(): HasMany
        {
            return $this->hasMany(PostComment::class, 'node_id', 'comment_id')->where('node_type', 'comment');
        }

        public function user(): BelongsTo
        {
            return $this->belongsTo(User::class, 'user_id', 'user_id');
        }
    }
