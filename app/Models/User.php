<?php

    namespace App\Models;

    // use Illuminate\Contracts\Auth\MustVerifyEmail;
    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Foundation\Auth\User as Authenticatable;
    use Illuminate\Notifications\Notifiable;
    use Laravel\Sanctum\HasApiTokens;

    class User extends Authenticatable
    {
        /** @use HasFactory<\Database\Factories\UserFactory> */
        use HasFactory, Notifiable, HasApiTokens;

        protected $primaryKey = 'user_id';

        /**
         * The attributes that are mass assignable.
         *
         * @var list<string>
         */
        protected $fillable = [
            "user_id",
            "user_master_account",
            "user_group",
            "user_group_custom",
            "user_demo",
            "user_name",
            "user_email",
            "user_email_verified",
            "user_email_verification_code",
            "user_phone",
            "user_phone_verified",
            "user_phone_verification_code",
            "user_password",
            "user_two_factor_enabled",
            "user_two_factor_type",
            "user_two_factor_key",
            "user_two_factor_gsecret",
            "user_activated",
            "user_approved",
            "user_reseted",
            "user_reset_key",
            "user_subscribed",
            "user_package",
            "user_package_videos_categories",
            "user_package_blogs_categories",
            "user_subscription_date",
            "user_boosted_posts",
            "user_boosted_pages",
            "user_started",
            "user_verified",
            "user_banned",
            "user_banned_message",
            "user_live_requests_counter",
            "user_live_requests_lastid",
            "user_live_messages_counter",
            "user_live_messages_lastid",
            "user_live_notifications_counter",
            "user_live_notifications_lastid",
            "user_latitude",
            "user_longitude",
            "user_location_updated",
            "user_firstname",
            "user_lastname",
            "user_gender",
            "user_picture",
            "user_picture_id",
            "user_cover",
            "user_cover_id",
            "user_cover_position",
            "user_album_pictures",
            "user_album_covers",
            "user_album_timeline",
            "user_pinned_post",
            "user_registered",
            "user_last_seen",
            "user_first_failed_login",
            "user_failed_login_ip",
            "user_failed_login_count",
            "user_country",
            "user_birthdate",
            "user_relationship",
            "user_biography",
            "user_website",
            "user_work_title",
            "user_work_place",
            "user_work_url",
            "user_current_city",
            "user_hometown",
            "user_edu_major",
            "user_edu_school",
            "user_edu_class",
            "user_social_facebook",
            "user_social_twitter",
            "user_social_youtube",
            "user_social_instagram",
            "user_social_twitch",
            "user_social_linkedin",
            "user_social_vkontakte",
            "user_profile_background",
            "user_chat_enabled",
            "user_newsletter_enabled",
            "user_tips_enabled",
            "user_privacy_chat",
            "user_privacy_poke",
            "user_privacy_gifts",
            "user_privacy_wall",
            "user_privacy_gender",
            "user_privacy_birthdate",
            "user_privacy_relationship",
            "user_privacy_basic",
            "user_privacy_work",
            "user_privacy_location",
            "user_privacy_education",
            "user_privacy_other",
            "user_privacy_friends",
            "user_privacy_followers",
            "user_privacy_photos",
            "user_privacy_pages",
            "user_privacy_groups",
            "user_privacy_events",
            "user_privacy_subscriptions",
            "email_post_likes",
            "email_post_comments",
            "email_post_shares",
            "email_wall_posts",
            "email_mentions",
            "email_profile_visits",
            "email_friend_requests",
            "email_user_verification",
            "email_user_post_approval",
            "email_admin_verifications",
            "email_admin_post_approval",
            "email_admin_user_approval",
            "facebook_connected",
            "facebook_id",
            "google_connected",
            "google_id",
            "twitter_connected",
            "twitter_id",
            "instagram_connected",
            "instagram_id",
            "linkedin_connected",
            "linkedin_id",
            "vkontakte_connected",
            "vkontakte_id",
            "wordpress_connected",
            "wordpress_id",
            "sngine_connected",
            "sngine_id",
            "user_referrer_id",
            "points_earned",
            "user_points",
            "user_wallet_balance",
            "user_affiliate_balance",
            "user_market_balance",
            "user_funding_balance",
            "user_monetization_enabled",
            "user_monetization_chat_price",
            "user_monetization_call_price",
            "user_monetization_min_price",
            "user_monetization_plans",
            "user_monetization_balance",
            "chat_sound",
            "notifications_sound",
            "onesignal_user_id",
            "onesignal_android_user_id",
            "onesignal_ios_user_id",
            "user_language",
            "user_free_tried",
            "coinbase_hash",
            "coinbase_code",
            # New
            "user_interests",
            "device_type",
            "device_token"
        ];

        public $timestamps = FALSE;

        /**
         * The attributes that should be hidden for serialization.
         *
         * @var list<string>
         */
        protected $hidden = [
            'user_password',
            // 'remember_token',
        ];

        protected $appends = [
            'is_following'
        ];

        /**
         * Get the attributes that should be cast.
         *
         * @return array<string, string>
         */
        protected function casts(): array
        {
            return [
                //'email_verified_at' => 'datetime',
                'user_password' => 'hashed',
                'user_interests' => 'array',
            ];
        }

        public function getIsFollowingAttribute()
        {
            return Following::where('user_id', request()->user()->user_id ?? null)->where('following_id', $this->attributes['user_id'])->count();
        }

        public function userGender()
        {
            // return $this->belongsTo(Gender::class, 'gender_id', 'user_gender');
            return $this->belongsTo(Gender::class, 'user_gender', 'gender_id');
        }

        public function userCountry()
        {
            return $this->belongsTo(Country::class, 'user_country', 'country_id');
        }

        public function followings()
        {
            return $this->hasMany(Following::class, 'user_id', 'user_id');
        }

        public function followers()
        {
            return $this->hasMany(Following::class, 'following_id', 'user_id');
        }
    }
