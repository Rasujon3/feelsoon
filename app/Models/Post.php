<?php

    namespace App\Models;

    use Illuminate\Database\Eloquent\Model;
    use Illuminate\Support\Facades\DB;

    class Post extends Model
    {
        protected $primaryKey = 'post_id';

        public $timestamps = FALSE;

        protected $fillable = [
            "user_id",
            "user_type",
            "in_group",
            "group_id",
            "group_approved",
            "in_event",
            "event_id",
            "event_approved",
            "in_wall",
            "wall_id",
            "post_type",
            "colored_pattern",
            "origin_id",
            "time",
            "location",
            "privacy",
            "text",
            "feeling_action",
            "feeling_value",
            "boosted",
            "boosted_by",
            "comments_disabled",
            "is_hidden",
            "for_adult",
            "is_anonymous",
            "reaction_like_count",
            "reaction_love_count",
            "reaction_haha_count",
            "reaction_yay_count",
            "reaction_wow_count",
            "reaction_sad_count",
            "reaction_angry_count",
            "comments",
            "shares",
            "views",
            "post_rate",
            "points_earned",
            "tips_enabled",
            "for_subscriptions",
            "subscriptions_image",
            "is_paid",
            "post_price",
            "paid_text",
            "paid_image",
            "processing",
            "pre_approved",
            "has_approved",
            "post_latitude",
            "post_longitude",
            "deleted_at",
            "photo_type",
            "music_id",
            "parent_post_id"
        ];

        protected $appends = [
            'is_like',
            'is_shared'
        ];

        public function getIsLikeAttribute()
        {
            return DB::table('posts_reactions')->where('user_id', request()->user()->user_id ?? NULL)->where('post_id', $this->attributes['post_id'])->count();
        }

        public function getIsSharedAttribute()
        {
            return DB::table('posts_shares')->where('user_id', request()->user()->user_id ?? NULL)->where('post_id', $this->attributes['post_id'])->count();
        }

        public function user()
        {
            return $this->belongsTo(User::class, 'user_id', 'user_id');
        }

        public function photos()
        {
            return $this->hasMany(PostPhoto::class, 'post_id', 'post_id');
        }

        public function videos()
        {
            return $this->hasMany(PostVideo::class, 'post_id', 'post_id');
        }

        public function shared()
        {
            return $this->hasMany(PostShare::class, 'post_id', 'post_id');
        }
        public function musics()
        {
            return $this->belongsTo(Music::class, 'music_id', 'id');
        }

    }
