<?php

    namespace App\Http\Resources\Api\V1\Modules;

    use Illuminate\Http\Request;
    use Illuminate\Http\Resources\Json\JsonResource;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Log;

    class PostResource extends JsonResource
    {
        /**
         * Transform the resource into an array.
         *
         * @return array<string, mixed>
         */
        public function toArray(Request $request): array
        {
            $postTime = Carbon::parse($this->time);
            $nowTime = Carbon::now();
            $diff = $postTime->diff($nowTime);

            // Format the difference in H:i:s format
            // $timeDiff = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s);

            $timeDiff = '';
            if($diff->h > 0) {
                $timeDiff = $diff->h . ' hour ago';
            }
            else if($diff->i > 0) {
                $timeDiff = $diff->h . ' minute(s) ago';
            }
            else if($diff->i > 0) {
                $timeDiff = $diff->s . ' seconds ago';
            }

            return [
                'post_id' => $this->post_id,
                'user_id' => $this->user_id,
                'user_name' => $this->user->user_name,
                'user_firstname' => $this->user->user_firstname,
                'user_lastname' => $this->user->user_lastname,
                'user_picture' => $this->user->user_picture,
                'user_type' => $this->user_type,
                'post_type' => $this->post_type,
                'photo_type' => $this->photo_type,
                'time' => $this->time,
                // 'time' => $timeDiff,
                'location' => $this->location,
                'colored_pattern' => $this->colored_pattern,
                'privacy' => $this->privacy,
                'text' => $this->text,
                'feeling_action' => $this->feeling_action,
                'feeling_value' => $this->feeling_value,
                'comments_disabled' => $this->comments_disabled ?? '0',
                'for_adult' => $this->for_adult,
                'is_anonymous' => $this->is_anonymous,
                'reaction_like_count' => $this->reaction_like_count ?? 0,
                'reaction_love_count' => $this->reaction_love_count ?? 0,
                'reaction_haha_count' => $this->reaction_haha_count ?? 0,
                'reaction_yay_count' => $this->reaction_yay_count ?? 0,
                'reaction_wow_count' => $this->reaction_wow_count ?? 0,
                'reaction_sad_count' => $this->reaction_sad_count ?? 0,
                'reaction_angry_count' => $this->reaction_angry_count ?? 0,
                'comments' => $this->comments,
                'shares' => $this->shares,
                'views' => $this->views,
                'pre_approved' => $this->pre_approved,
                'has_approved' => $this->has_approved,
                'post_latitude' => $this->post_latitude,
                'post_longitude' => $this->post_longitude,
                'is_like' => $this->is_like,
                'is_follow' => auth()->user()->followings()->where('following_id', $this->user_id)->exists(),
                'is_shared' => $this->is_shared,
                'source' => !empty($this->source) ? $this->source : NULL,
                'source_video' => !empty($this->source_video) ? $this->source_video : NULL,
                'thumbnail' => !empty($this->thumbnail) ? $this->thumbnail : NULL,
                'category_name' => $this->category_name,
                // 'post_time' => $timeDiff,
                'photos' => $this->photos->count() > 0 ? $this->photos->pluck('source')->toArray() : [],
                'music' => !empty($this->musics) ? [
                    'id' => $this->musics->id,
                    'title' => $this->musics->title,
                    'file_path' => $this->musics->file_path,
                ] : null,
            ];
        }

        public function __construct($resource)
        {
            parent::__construct($resource);
        }
    }
