<?php

    namespace App\Http\Resources\Api\V1\Modules;

    use Illuminate\Http\Request;
    use Illuminate\Http\Resources\Json\JsonResource;

    class NotificationResource extends JsonResource
    {
        public function toArray(Request $request): array
        {
            $photo = $this->post->photos[0]->source ?? null;
            $video = $this->post->videos[0]->source ?? null;

            $post = null;
            if(!empty($this->post)) {

                $post =  $this->post->only(
                    'post_type', 'time', 'location', 'text', 'feeling_action', 'feeling_value', 'reaction_like_count', 'thumbnail'
                );
                $post['photo'] = $photo;
                $post['video'] = $video;
            }

            return [
                'to_user_id' => $this->to_user_id,
                'from_user_id' => $this->from_user_id,
                'from_user_type' => $this->from_user_type,
                'action' => $this->action,
                'node_type' => $this->node_type,
                'node_id' => $this->node_id,
                'node_url' => $this->node_url,
                'notify_id' => $this->notify_id,
                'message' => $this->message,
                'time' => $this->time,
                'seen' => $this->seen,
                "is_following" => (bool) $this->is_following,
                "is_requested" => (bool) $this->is_requested,
                "is_blocked" => (bool) $this->is_blocked,
                'from_user' => !empty($this->fromUser) ? $this->fromUser->only(
                    'user_name', 'user_firstname', 'user_lastname', 'user_email', 'user_phone', 'user_picture'
                ) : NULL,
                'to_user' => !empty($this->toUser) ? $this->toUser->only(
                    'user_name', 'user_firstname', 'user_lastname', 'user_email', 'user_phone', 'user_picture'
                ) : NULL,
                'post' => $post,
            ];
        }
    }
