<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Http\Controllers\Controller;
    use App\Http\Resources\Api\V1\Modules\CommentResource;
    use App\Models\Notification;
    use App\Models\Post;
    use App\Models\PostComment;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\UploadFileToStorage;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Validator;

    class CommentController extends Controller
    {
        use UniformResponseTrait, UploadFileToStorage;

        public function index(Request $request)
        {
            $rules = [
                'post_id' => 'required_if:comment_id,null',
                'comment_id' => 'required_if:post_id,null'
            ];

            $validator = Validator::make($request->post(), $rules);
            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            // $loginUserId = auth('sanctum')->id();

            $comments = PostComment::selectRaw('comment_id, node_id as post_id, posts_comments.user_id, text, image, voice_note, time, reaction_like_count
                    , users.user_name, users.user_firstname, users.user_lastname, users.user_picture')
                ->leftJoin('users', 'posts_comments.user_id', 'users.user_id')
                ->when(!empty($request->post('post_id')), function ($q) {
                    $q->where('node_id', request()->post('post_id'))->where('node_type', 'post');
                })
                ->when(!empty($request->post('comment_id')), function ($q) {
                    $q->where('node_id', request()->post('comment_id'))->where('node_type', 'comment');
                })
                ->orderByDesc('comment_id');

            $comments = $comments->with(['commentReplies' => function ($q) {
                $q->orderByDesc('comment_id')->take(5)->with('user');
            }])->withCount('commentReplies')->paginate(20);
            $totalRecords = $comments->total();
            $totalPages = $comments->lastPage();
            $comments = CommentResource::collection($comments->items());
            $currentPage = $request->post('page', 1);

            $response = [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'comments' => !empty($comments) ? $comments : NULL,
            ];

            return $this->sendResponse($totalRecords > 0 ? TRUE : FALSE, 'Comments data get successfully', $response);
        }

        public function store(Request $request)
        {
            $rules = [
                'node_id' => 'required',
                'node_type' => 'required|in:post,comment',
                'comment_type' => 'required',
                'text' => 'required_if:comment_type,==,1',
//                'image' => 'required_if:comment_type,2|file',
//                'voice_note' => 'required_if:comment_type,3|file'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $nodeId = $request->post('node_id');
            $nodeType = $request->post('node_type');
            $commentType = $request->post('comment_type');

            $requestData = [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'user_id' => $loginUserId,
                'user_type' => 'user',
                'text' => $request->post('text') ?? '',
                'time' => now()->format("Y-m-d H:i:s")
            ];

            if ($commentType == 2) {
                $media = $request->file('image');
                $fileName = time() . '.' . $media->getClientOriginalName();

                $requestData['image'] = $this->verifyAndUpload($request->file('image'), $fileName, 'posts/' . date("Y") . '/' . date("m") . '/comments/' . $nodeId);
            }
            else if ($commentType == 3) {
                $media = $request->file('voice_note');
                $fileName = time() . '.' . $media->getClientOriginalName();

                $requestData['voice_note'] = $this->verifyAndUpload($request->file('image'), $fileName, 'posts/' . date('Y') . '/' . date('m') . '/comments/' . $nodeId);
            }

            // $comment = DB::table('posts_comments')->insertGetId($requestData);
            $comment = PostComment::create($requestData);
            if (!empty($comment)) {

                if ($nodeType == 'post') {
                    Post::where('post_id', $nodeId)->increment('comments');

                    $postUser = Post::where('post_id', $nodeId)->first();

                    Notification::create([
                        'to_user_id' => $postUser->user_id ?? 0,
                        'from_user_id' => $loginUserId,
                        'from_user_type' => 'user',
                        'action' => 'comment',
                        'node_type' => 'post',
                        'node_url' => $nodeId,
                        'message' => 'commented on your post',
                        'time' => now()->format('Y-m-d H:i:s')
                    ]);

                    if(!empty($postUser->user->device_token)) {
                        $pushMessage = auth('sanctum')->user()->first_name . ' commented on your post.';
                        $pushData = [
                            'user_id' => $loginUserId,
                            'post_id' => $nodeId
                        ];
                        $this->sendNotification($postUser->user->device_token. 'New Comment', $pushMessage, $pushData);
                    }
                }
                else {
                    Notification::create([
                        'to_user_id' => PostComment::where('comment_id', $nodeId)->first()->user_id ?? 0,
                        'from_user_id' => $loginUserId,
                        'from_user_type' => 'user',
                        'action' => 'comment',
                        'node_type' => 'comment',
                        'node_url' => $nodeId,
                        'message' => 'replied on your comment',
                        'time' => now()->format('Y-m-d H:i:s')
                    ]);

                    if(!empty($postUser->user->device_token)) {
                        $pushMessage = auth('sanctum')->user()->first_name . ' replied on your comment.';
                        $pushData = [
                            'user_id' => $loginUserId,
                            'comment_id' => $nodeId
                        ];
                        $this->sendNotification($postUser->user->device_token. 'New Comment', $pushMessage, $pushData);
                    }
                }

                return $this->sendResponse(TRUE, 'Comment added successfully.', CommentResource::make($comment->fresh()));
            }

            return $this->sendResponse(FALSE, 'Something went wrong, Please try again.');
        }

        public function storeLike(Request $request)
        {
            $rules = [
                'comment_ids' => 'required',
                'reaction' => 'required'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $reaction = $request->post('reaction');
            $commentIds = explode(',', $request->post('comment_ids'));

            $message = NULL;
            foreach ($commentIds as $commentId) {
                $postReaction = DB::table('posts_comments_reactions')
                    ->where('comment_id', $commentId)
                    ->where('user_id', $loginUserId)
                    ->where('reaction', $reaction)
                    ->first();

                if (!empty($postReaction) && !empty($postReaction->id)) {
                    DB::table('posts_comments_reactions')->where('id', $postReaction->id)->delete();
                    if (empty($message)) {
                        $message = 'Comment unliked successfully.';
                    }
                    DB::table('posts_comments')->where('comment_id', $commentId)->decrement('reaction_like_count');
                }
                else {
                    $message = 'Comment liked successfully';
                    DB::table('posts_comments_reactions')->insert([
                        'comment_id' => $commentId,
                        'user_id' => $loginUserId,
                        'reaction' => $reaction,
                        'reaction_time' => now()->format('Y-m-d H:i:s')
                    ]);
                    DB::table('posts_comments')->where('comment_id', $commentId)->increment('reaction_like_count');

                    $postComment = PostComment::find($commentId);
                    Notification::create([
                        'to_user_id' => $postComment->user_id ?? 0,
                        'from_user_id' => $loginUserId,
                        'from_user_type' => 'user',
                        'action' => 'react_like',
                        'node_type' => 'comment',
                        'node_url' => $commentId,
                        'message' => 'liked your comment',
                        'time' => now()->format('Y-m-d H:i:s')
                    ]);

                    if(!empty($postComment->user->device_token)) {
                        $pushMessage = auth('sanctum')->user()->first_name . ' liked your comment.';
                        $pushData = [
                            'user_id' => $loginUserId,
                            'comment_id' => $commentId
                        ];
                        $this->sendNotification($postUser->user->device_token. 'Liked a Comment', $pushMessage, $pushData);
                    }
                }
            }

            return $this->sendResponse(TRUE, $message);
        }
    }
