<?php

    namespace App\Http\Resources\Api\V1\Modules;

    use Illuminate\Http\Request;
    use Illuminate\Http\Resources\Json\JsonResource;
    use Carbon\Carbon;

    class CommentResource extends JsonResource
    {
        /**
         * Transform the resource into an array.
         *
         * @return array<string, mixed>
         */
        public function toArray(Request $request): array
        {
            $commentReplies = [];
            if(!empty($this->commentReplies)) {
                foreach ($this->commentReplies as $reply) {

                    $commentTime = Carbon::parse($reply->time);
                    $nowTime = Carbon::now();
                    $diff = $commentTime->diff($nowTime);

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

                    $commentReplies[] = [
                        'comment_id' => $reply->comment_id,
                        'post_id' => $reply->post_id,
                        'user_id' => $reply->user_id,
                        'user_name' => $reply->user->user_name,
                        'user_firstname' => $reply->user->user_firstname,
                        'user_lastname' => $reply->user->user_lastname,
                        'user_picture' => !empty($reply->user->user_picture) ? $reply->user->user_picture : null,
                        'text' => $reply->text,
                        'image' => !empty($reply->image) ? $reply->image : null,
                        'voice_note' => $reply->voice_note,
                        // 'time' => $reply->time,
                        'time' => $timeDiff,
                        'reaction_like_count' => $reply->reaction_like_count,
                        'is_like' => $reply->is_like,
                        'replies' => $reply->comment_replies_count,
                        'comments' => []
                    ];
                }
            }

            $commentTime = Carbon::parse($this->time);
            $nowTime = Carbon::now();
            $diff = $commentTime->diff($nowTime);

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
                'comment_id' => $this->comment_id,
                'post_id' => $this->post_id,
                'user_id' => $this->user_id,
                'user_name' => $this->user_name,
                'user_firstname' => $this->user_firstname,
                'user_lastname' => $this->user_lastname,
                'user_picture' => !empty($this->user_picture) ? $this->user_picture : null,
                'text' => $this->text,
                'image' => !empty($this->image) ? $this->image : null,
                'voice_note' => $this->voice_note,
                // 'time' => $this->time,
                'time' => $timeDiff,
                'reaction_like_count' => $this->reaction_like_count,
                'is_like' => $this->is_like,
                'replies' => $this->comment_replies_count,
                'comments' => $commentReplies
            ];
        }

        public function __construct($resource)
        {
            parent::__construct($resource);
        }
    }
