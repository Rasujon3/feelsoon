<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Http\Controllers\Controller;
    use App\Models\Notification;
    use App\Models\Post;
    use App\Traits\Api\UniformResponseTrait;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Validator;

    class LikeController extends Controller
    {
        use UniformResponseTrait;

        public function index(Request $request)
        {
            $rules = [
                'post_id' => 'required'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $postId = $request->post('post_id');
            $likes = DB::table('posts_reactions')
                ->selectRaw('id, post_id, posts_reactions.user_id, reaction, reaction_time, user_name, user_firstname, user_lastname, user_picture, user_latitude, user_longitude')
                ->where('post_id', $postId)
                ->where('reaction', 'like')
                ->join('users', 'posts_reactions.user_id', 'users.user_id')
                ->paginate(20);

            $totalRecords = $likes->total();
            $totalPages = $likes->lastPage();
            $likes = $likes->items();
            $currentPage = $request->post('page', 1);

            $response = [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'users' => !empty($likes) ? $likes : NULL,
            ];

            return $this->sendResponse($totalRecords > 0, 'Posts data get successfully', $response);
        }

        public function store(Request $request)
        {
            $rules = [
                'post_ids' => 'required',
                'reaction' => 'required'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $reaction = $request->post('reaction');
            $postIds = explode(',', $request->post('post_ids'));

            $message = NULL;
            foreach ($postIds as $postId) {
                $postReaction = DB::table('posts_reactions')
                    ->where('post_id', $postId)
                    ->where('user_id', $loginUserId)
                    ->where('reaction', $reaction)
                    ->first();

                if (!empty($postReaction) && !empty($postReaction->id)) {
                    DB::table('posts_reactions')->where('id', $postReaction->id)->delete();
                    if (empty($message)) {
                        $message = 'Post unliked successfully.';
                    }
                    Post::where('post_id', $postId)->decrement('reaction_like_count');
                }
                else {
                    $message = 'Post liked successfully';
                    DB::table('posts_reactions')->insert([
                        'post_id' => $postId,
                        'user_id' => $loginUserId,
                        'reaction' => $reaction,
                        'reaction_time' => now()->format("Y-m-d H:i:s")
                    ]);
                    Post::where('post_id', $postId)->increment('reaction_like_count');

                    $postUser = Post::where('post_id', $postId)->first();
                    Notification::create([
                        'to_user_id' => $postUser->user_id ?? 0,
                        'from_user_id' => $loginUserId,
                        'from_user_type' => 'user',
                        'action' => 'react_like',
                        'node_type' => 'post',
                        'node_url' => $postId,
                        'message' => 'liked your post',
                        'time' => now()->format('Y-m-d H:i:s')
                    ]);

                    if(!empty($postUser->user->device_token)) {
                        $pushMessage = auth('sanctum')->user()->first_name . ' liked your post.';
                        $pushData = [
                            'user_id' => $loginUserId,
                            'post_id' => $postId
                        ];
                        $this->sendNotification($postUser->user->device_token. 'Liked a Post', $pushMessage, $pushData);
                    }
                }
            }

            return $this->sendResponse(TRUE, $message);
        }
    }
