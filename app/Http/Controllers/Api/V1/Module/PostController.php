<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Http\Controllers\Controller;
    use App\Http\Resources\Api\V1\Modules\PostResource;
    use App\Models\Post;
    use App\Models\PostPhoto;
    use App\Models\PostPhotoAlbum;
    use App\Models\PostView;
    use App\Models\User;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\UploadFileToStorage;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Validator;
    use App\Http\Resources\Api\V1\UserResource;
    use Exception;

    class PostController extends Controller
    {
        use UniformResponseTrait, UploadFileToStorage;

        public function index(Request $request): JsonResponse
        {
            $search = $request->post('search');
            $isLatest = $request->post('is_latest');
            $sortBy = $request->post('sort_by');
            $sortOrder = $request->post('sort_order');
            $userType = $request->post('user_type', 'all'); // all, followings, followers, own, user
            $postType = $request->post('post_type', 'all');
            $userId = $request->post('user_id');
            $isTesting = $request->post('is_testing', 0);
            $loginUserId = auth('sanctum')->id();

            // Store post views
            /*$viewIds = $request->post('view_ids');
            if (!empty($viewIds)) {
                $viewIds = explode(',', $viewIds);
                foreach ($viewIds as $viewId) {
                    $viewRow = [
                        'post_id' => $viewId,
                        'user_id' => $loginUserId,
                    ];
                    $isAdded = PostView::firstOrCreate($viewRow, [
                        'view_date' => now()->format('Y-m-d H:i:s'),
                    ]);
                    if ($isAdded->wasRecentlyCreated == true) {
                        Post::where('post_id', $viewId)->increment('views');
                    }
                }
            }*/

            $posts = Post::selectRaw('posts.*, posts_videos.source as source_video, posts_videos.thumbnail, category_name')
                ->where('user_id', ($userType == 'own' ? '=' : '!='), $loginUserId)
                ->when($userType != 'own' && $userType != 'user' && $isTesting == 0, function ($q) {
                    $q->where('time', '>=', now()->subDay()->format("Y-m-d H:i:s"));
                })
                ->when(!empty($postType) && $postType != 'all', function ($q) use ($postType) {
                    $q->where('post_type', $postType);
                })
                ->when(!empty($postType) && $postType == 'all', function ($q) use ($loginUserId) {
                    $q->whereRaw("user_id not in (select blocked_user_id from users_interests where user_id = $loginUserId)");
                    $q->whereRaw("user_id not in (select blocked_id from users_blocks where user_id = $loginUserId)");
                })
                ->when(!empty($search), function ($q) use ($search) {
                    $q->where(function ($q1) use ($search) {
                        $q1->where('text', 'LIKE', "%$search%")
                            ->orWhere('feeling_value', 'LIKE', "%$search%")
                            ->orWhereHas('user', function ($s1) use($search) {
                                $s1->where('user_firstname', 'like', "%$search%");
                                $s1->orWhere('user_lastname', 'like', "%$search%");
                                $s1->orWhere('user_name', 'like', "%$search%");
                            })
    //                            ->orWhere('user_lastname', 'LIKE', "%$search%")
    //                            ->orWhere('user_biography', 'LIKE', "%$search%")
                        ;
                    });
                })
                ->when(!empty($userType) && $userType != 'all', function ($q) use ($userType, $loginUserId, $userId) {
                    if ($userType == 'following') {
                        $q->whereRaw('user_id in (select following_id from followings where user_id = ' . $loginUserId . ')');
                    } else if ($userType == 'followers') {
                        $q->whereRaw('user_id in (select user_id from followings where following_id = ' . $loginUserId . ')');
                    } else if ($userType == 'user') {
                        $q->where('user_id', $userId);
                    }
                })
                ->when(!empty($sortBy) && !empty($sortOrder), function ($q) use ($sortBy, $sortOrder) {
                    $q->orderBy($sortBy, $sortOrder);
                })
                ->when(empty($isLatest) && (empty($sortBy) || empty($sortOrder)), function ($q) use ($userType) {
                    if ($userType != 'own') {
                        $q->inRandomOrder();
                    } else {
                        $q->orderBy('post_id', 'desc');
                    }
                })
                // ->leftJoin('posts_photos', 'posts.post_id', 'posts_photos.post_id')
                ->leftJoin('posts_videos', 'posts.post_id', 'posts_videos.post_id')
                ->leftJoin('posts_videos_categories', 'posts_videos.category_id', 'posts_videos_categories.category_id');

            //$posts = $posts->dd();
            $posts = $posts->whereNull('deleted_at')->where(function ($q) {
                $q->whereHas('photos')->orWhereHas('videos');
            })
//             ->dd();
            ->paginate(20);
            $totalRecords = $posts->total();
            $totalPages = $posts->lastPage();
            $posts = PostResource::collection($posts->items());
            $currentPage = $request->post('page', 1);

            $response = [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'posts' => !empty($posts) ? $posts : null,
            ];

            return $this->sendResponse(
                $totalRecords > 0 ? true : false,
                'Posts data get successfully',
                $response
            );
        }

        public function show($postId, Request $request)
        {
            // $loginUserId = auth('sanctum')->id();

            $post = Post::selectRaw('posts.*, posts_photos.source, posts_videos.source as source_video, posts_videos.thumbnail, category_name')
                ->leftJoin('posts_photos', 'posts.post_id', 'posts_photos.post_id')
                ->leftJoin('posts_videos', 'posts.post_id', 'posts_videos.post_id')
                ->leftJoin('posts_videos_categories', 'posts_videos.category_id', 'posts_videos_categories.category_id')
                ->whereNull('deleted_at')
                ->find($postId);

            $message = 'Post not found';
            if (!empty($post)) {
                $message = 'Posts data get successfully';
                $post = PostResource::make($post);
            }

            return $this->sendResponse(!empty($post) > 0 ? true : false, $message, $post);
        }

        public function categories(): JsonResponse
        {
            $categories = DB::table('posts_videos_categories')->orderBy('category_order')->get();

            return $this->sendResponse(true, 'Categories data get successfully', $categories);
        }

        public function store(Request $request): JsonResponse
        {
            try {
                $rules = [
                    // Common Input for all type
                    'user_type' => 'required|in:user,page', // user, page
                    'privacy' => 'required|in:public,private', // public, private
                    'text' => 'nullable|string', // content
                    'post_latitude' => 'required',
                    'post_longitude' => 'required',
                    'post_type' => 'nullable|in:photos,videos', // photos, videos
                    'medias' => 'required|array',
                    'medias.*' => 'file|mimes:jpg,jpeg,png,gif,mp4|max:10240', // 10MB
                    'location' => 'nullable|string',
                    'music_id' => 'nullable|exists:musics,id',
                ];

                # $validator = Validator::make($request->post(), $rules);
                $validator = Validator::make($request->all(), $rules);
                if ($validator->fails()) {
                    $error = $validator->errors()->all(':message');

                    return $this->sendResponse(false, $error[0]);
                }

                $loginUserId = auth('sanctum')->id();

                /*if ($request->post('post_type') == 'photos' && empty($request->file('medias'))) {
                return $this->sendResponse(FALSE, 'Media file is required.');
                }
                if ($request->post('post_type') == 'videos' && empty($request->file('media'))) {
                return $this->sendResponse(FALSE, 'Media file is required.');
                }*/
                if (empty($request->file('medias'))) {
                    return $this->sendResponse(false, 'Media file is required.');
                }

                $requestData = $request->post();
                $requestData['post_type'] = $requestData['post_type'] ?? '';
                $requestData['photo_type'] = $requestData['photo_type'] ?? '1';
                $requestData['user_id'] = auth('sanctum')->id();
                $requestData['time'] = now()->format("Y-m-d H:i:s");
                $requestData['has_approved'] = "1";
                $requestData['for_adult'] = $requestData['for_adult'] ?? "0";
                $requestData['is_anonymous'] = $requestData['is_anonymous'] ?? "0";
                $requestData['post_latitude'] = $requestData['post_latitude'] ?? "0";
                $requestData['post_longitude'] = $requestData['post_longitude'] ?? "0";
                $requestData['comments_disabled'] = $requestData['comments_disabled'] ?? "0";
                $post = Post::create($requestData);

                if (!empty($post)) {
                    if ($requestData['post_type'] === 'photos') {
                        $album = PostPhotoAlbum::where('user_id', $loginUserId)
                            ->where('user_type', 'user')
                            ->where('privacy', $post->privacy)
                            ->first();

                        if (empty($album)) {
                            $album = PostPhotoAlbum::create([
                                'user_id' => $loginUserId,
                                'user_type' => $requestData['user_type'],
                                'title' => $requestData['text'],
                                'privacy' => $requestData['privacy'],
                            ]);
                        }

                        // Multiple file upload
                        $medias = $request->file('medias');
                        foreach ($medias as $media) {
                            $fileName = time() . '.' . $media->getClientOriginalName();

                            PostPhoto::create([
                                'post_id' => $post->post_id,
                                'album_id' => $album->album_id,
                                'source' => $this->verifyAndUpload($media, $fileName, 'photos/' . date('Y') . '/' . date('m')),
                            ]);
                        }
                    } else if ($requestData['post_type'] == 'videos') {
                        $thumbnail = $request->file('thumbnail');
                        $thumbnailPath = null;
                        if (!empty($thumbnail)) {
                            $fileName = time() . '.' . $thumbnail->getClientOriginalName();
                            $thumbnailPath = $this->verifyAndUpload($thumbnail, $fileName, 'videos/' . date('Y') . '/' . date('m'));
                        }

                        // Multiple file upload
                        $medias = $request->file('medias');
                        foreach ($medias as $media) {
                            // $fileName = time() . '.' . $media->getClientOriginalName();
                            $fileName = $media->getClientOriginalName();
                            $fileExtension = $media->getClientOriginalExtension();
                            $sourceVideo = $this->verifyAndUpload($media, $fileName, 'videos/' . $post->post_id, true);

                            if ($fileExtension == 'm3u8') {
                                DB::table('posts_videos')->insert([
                                    'post_id' => $post->post_id,
                                    'category_id' => $requestData['category_id'],
                                    'source' => $sourceVideo,
                                    'thumbnail' => $thumbnailPath,
                                ]);
                            }
                        }
                    }

                    if(!empty($request->post('mention_user_ids'))) {
                        foreach($request->post('mention_user_ids') as $mentionUserId) {
                            $newPost = $post->replicate(); // Clone the original post
                            $newPost->parent_post_id = $post->post_id;
                            $newPost->user_id = $mentionUserId;
                            $newPost->save();

                            if ($requestData['post_type'] === 'photos') {
                                $album = PostPhotoAlbum::where('user_id', $mentionUserId)
                                    ->where('user_type', 'user')
                                    ->where('privacy', $post->privacy)
                                    ->first();

                                if (empty($album)) {
                                    $album = PostPhotoAlbum::create([
                                        'user_id' => $mentionUserId,
                                        'user_type' => $requestData['user_type'],
                                        'title' => $requestData['text'],
                                        'privacy' => $requestData['privacy'],
                                    ]);
                                }

                                // Multiple file upload
                                $medias = $request->file('medias');
                                foreach ($medias as $media) {
                                    $fileName = time() . '.' . $media->getClientOriginalName();

                                    PostPhoto::create([
                                        'post_id' => $newPost->post_id,
                                        'album_id' => $album->album_id,
                                        'source' => $this->verifyAndUpload($media, $fileName, 'photos/' . date('Y') . '/' . date('m')),
                                    ]);
                                }
                            } else if ($requestData['post_type'] == 'videos') {
                                $thumbnail = $request->file('thumbnail');
                                $thumbnailPath = null;
                                if (!empty($thumbnail)) {
                                    $fileName = time() . '.' . $thumbnail->getClientOriginalName();
                                    $thumbnailPath = $this->verifyAndUpload($thumbnail, $fileName, 'videos/' . date('Y') . '/' . date('m'));
                                }

                                // Multiple file upload
                                $medias = $request->file('medias');
                                foreach ($medias as $media) {
                                    // $fileName = time() . '.' . $media->getClientOriginalName();
                                    $fileName = $media->getClientOriginalName();
                                    $fileExtension = $media->getClientOriginalExtension();
                                    $sourceVideo = $this->verifyAndUpload($media, $fileName, 'videos/' . $mentionUserId->post_id, true);

                                    if ($fileExtension == 'm3u8') {
                                        DB::table('posts_videos')->insert([
                                            'post_id' => $newPost->post_id,
                                            'category_id' => $requestData['category_id'],
                                            'source' => $sourceVideo,
                                            'thumbnail' => $thumbnailPath,
                                        ]);
                                    }
                                }
                            }

                            $mentionUser = User::find($mentionUserId);
                            if(!empty($mentionUser->device_token)) {
                                $pushMessage = auth('sanctum')->user()->first_name . ' mentioned you in their post.';
                                $pushData = [
                                    'user_id' => $loginUserId,
                                    'post_id' => $post->post_id,
                                    'clone_post_id' => $newPost->post_id
                                ];
                                $this->sendNotification($mentionUser->device_token. 'Mentioned', $pushMessage, $pushData);
                            }
                        }
                    }

                    /*
                    $post = Post::selectRaw(
                        'posts.*,
                        posts_photos.source,
                        posts_videos.source as source_video,
                        posts_videos.thumbnail,
                        musics.*,
                        category_name'
                    )
                        ->leftJoin('posts_photos', 'posts.post_id', 'posts_photos.post_id')
                        ->leftJoin('posts_videos', 'posts.post_id', 'posts_videos.post_id')
                        ->leftJoin('posts_videos_categories', 'posts_videos.category_id', 'posts_videos_categories.category_id')
                        ->leftJoin('musics', 'posts.music_id', 'musics.id')
                        ->find($post->post_id);
                    */

                    $post = Post::with(['user', 'photos', 'videos', 'musics', 'shared'])->find($post->post_id);

                    $post = PostResource::make($post) ?? null;

                    return $this->sendResponse(true, 'Post created successfully.', $post);
                }

                return $this->sendResponse(false, 'Something went wrong, Please try again');
            } catch (Exception $e) {

                // Log the error
                Log::error('Error in store Post: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return $this->sendResponse(false, 'Something went wrong!!!', []);
            }
        }

        public function views(Request $request): JsonResponse
        {
            $rules = [
                'post_id' => 'required',
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(false, $error[0]);
            }

            $postId = $request->post('post_id');
            $views = DB::table('posts_views')
                ->selectRaw('view_id, post_id, posts_views.user_id, view_date, user_name, user_firstname, user_lastname, user_picture, user_latitude, user_longitude')
                ->where('post_id', $postId)
                ->join('users', 'posts_views.user_id', 'users.user_id')
                ->paginate(20);

            $totalRecords = $views->total();
            $totalPages = $views->lastPage();
            $views = $views->items();
            $currentPage = $request->post('page', 1);

            $response = [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'users' => !empty($views) ? $views : null,
            ];

            return $this->sendResponse($totalRecords > 0, 'Posts views data get successfully', $response);
        }

        public function update(Request $request): JsonResponse
        {
            $rules = [
                // Common Input for all type
                'post_id' => 'required|exists:posts,post_id',
            ];

            $validator = Validator::make($request->post(), $rules);
            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(false, $error[0]);
            }

            // $loginUserId = auth('sanctum')->id();
            $postId = $request->post('post_id');

            $requestData = $request->post();
            $requestData['user_id'] = auth('sanctum')->id();
            $requestData['category_id'] = null;
            $requestData['view_ids'] = null;
            $requestData = array_filter($requestData);
            $post = Post::where('post_id', $postId)->update($requestData);

            // if (!empty($post)) {
            $post = Post::selectRaw('posts.*, posts_photos.source, posts_videos.source as source_video, posts_videos.thumbnail, category_name')
                ->leftJoin('posts_photos', 'posts.post_id', 'posts_photos.post_id')
                ->leftJoin('posts_videos', 'posts.post_id', 'posts_videos.post_id')
                ->leftJoin('posts_videos_categories', 'posts_videos.category_id', 'posts_videos_categories.category_id')
                ->find($postId);
            $post = PostResource::make($post) ?? null;

            return $this->sendResponse(true, 'Post updated successfully.', $post);
            // }

            // return $this->sendResponse(FALSE, 'Something went wrong, Please try again');
        }

        public function destroy(Request $request): JsonResponse
        {
            $rules = [
                'post_id' => 'required|exists:posts,post_id',
            ];

            $validator = Validator::make($request->post(), $rules);
            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(false, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $postId = $request->post('post_id');

            $post = Post::find($postId);

            if (!empty($post) && $post->user_id == $loginUserId) {
                $post->update(['deleted_at' => now()->format('Y-m-d H:i:s')]);

                Post::where('parent_post_id', $post->post_id)->update(['deleted_at' => now()->format('Y-m-d H:i:s')]);

                return $this->sendResponse(true, 'Post deleted successfully.');
            }

            return $this->sendResponse(false, 'Something went wrong, Please try again');
        }

        public function storeViews(Request $request)
        {
            $rules = [
                'post_id' => 'required'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $postId = $request->post('post_id');

            PostView::firstOrCreate([
                'post_id' => $postId,
                'user_id' => $loginUserId
            ], [
                'view_date' => now()->format("Y-m-d H:i:s")
            ]);
            Post::where('post_id', $postId)->increment('views');

            $message = 'Post view added successfully.';

            return $this->sendResponse(TRUE, $message);
        }

        public function myFollowers(Request $request)
        {
            try
            {
                $user_ids = DB::table('followings')
                    ->where('followings.following_id',auth()->user()->user_id)
                    ->where('followings.status',0)
                    ->pluck('user_id')

                    ->toArray();

                //return $user_ids;
                $users = User::with('followings','followers')->whereIn('user_id',$user_ids)->paginate(10);

                //$users = $users->withCount('followings', 'followers')->paginate(20);



                $totalRecords = $users->total();



                $totalPages = $users->lastPage();

                $allUsers = UserResource::collection($users->items());

                $currentPage = $request->get('page', 1);

                $response = [
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                    'current_page' => intval($currentPage),
                    'users' => !empty($users) ? $allUsers : [],
                ];

                return response()->json(['status'=>count($allUsers)>0, 'status_code'=>200, 'message'=>"Data Found", 'result'=>$response]);

            }catch(Exception $e){
                return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
            }

        }

        public function mySuggessions(Request $request)
        {
            $distance = 10;

            $user = User::where('user_id', auth()->id())->first();

            //return $user;

            $lat = $user->user_latitude;
            $lon = $user->user_longitude;
            $search = $request->input('search');

            try {

                $delete_ids = DB::table('trash_suggestions')->where('user_id',auth()->id())->pluck('suggested_id')->toArray();
                //return $delete_ids;
                $query = User::query();

                if ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('users.user_firstname', 'LIKE', "%$search%")
                            ->orWhere('users.user_lastname', 'LIKE', "%$search%")
                            ->orWhere('users.user_name', 'LIKE', "%$search%");
                    });
                }

                $users = $query->whereNotIN('user_id',$delete_ids)->where('user_id','!=',auth()->id())->withCount('followings', 'followers')
                    ->select(
                        "users.user_id",
                        "users.user_name",
                        "users.user_phone",
                        "users.user_latitude",
                        "users.user_longitude",
                        "users.user_firstname",
                        "users.user_lastname",
                        "users.user_picture",
                        "users.user_gender"
                    )
                    ->selectRaw("6371 * acos(cos(radians(?))
                      * cos(radians(users.user_latitude))
                      * cos(radians(users.user_longitude) - radians(?))
                      + sin(radians(?))
                      * sin(radians(users.user_latitude))) AS distance", [$lat, $lon, $lat])
                    ->whereNotNull('users.user_latitude')
                    ->whereNotNull('users.user_longitude')
                    //->having('distance', '<=', $distance)
                    ->orderBy('distance', 'ASC')
                    ->paginate(10);

                $response = [
                    'total_records' => $users->total(),
                    'total_pages'   => $users->lastPage(),
                    'current_page'  => intval($request->input('page', 1)),
                    'users'         => UserResource::collection($users->items()),
                ];

                return response()->json([
                    'status'      => true,
                    'status_code'=> 200,
                    'message'     => "Data Found",
                    'result'      => $response
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'status'  => false,
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        public function trashSuggestion(Request $request)
        {
            try
            {
                $count = DB::table('trash_suggestions')->where('user_id',auth()->user()->user_id)->where('suggested_id',$request->user_id)->count();
                if($count > 0)
                {
                    return response()->json(['status'=>false, 'message'=>'Already delete the user'],400);
                }
                $data = array();
                $data['user_id'] = auth()->user()->user_id;
                $data['suggested_id'] = $request->user_id;
                DB::table('trash_suggestions')->insert($data);
                return response()->json(['status'=>true, 'message'=>'Successfully removed the user from your suggestion list']);
            }catch (\Exception $e) {
                return response()->json([
                    'status'  => false,
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        public function myFriendLists(Request $request)
        {
            try
            {
                $user = User::where('user_id', auth()->id())->first();
                //where auth user follow
                $first_ids = DB::table('followings')
                    ->where('status',1)
                    ->where('user_id',auth()->id())
                    ->pluck('following_id')
                    ->toArray();

                //where anyone foller the auth user
                $second_ids = DB::table('followings')
                    ->where('status',1)
                    ->where('following_id',auth()->id())
                    ->pluck('user_id')
                    ->toArray();



                $user_ids = array_merge($first_ids,$second_ids);


                $users = User::with('followings','followers')->whereIn('user_id',$user_ids)->paginate(10);

                //$users = $users->withCount('followings', 'followers')->paginate(20);



                $totalRecords = $users->total();



                $totalPages = $users->lastPage();

                $allUsers = UserResource::collection($users->items());

                $currentPage = $request->get('page', 1);

                $response = [
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                    'current_page' => intval($currentPage),
                    'users' => !empty($users) ? $allUsers : [],
                ];

                return response()->json(['status'=>count($allUsers)>0, 'status_code'=>200, 'message'=>"Data Found", 'result'=>$response]);

            }catch (\Exception $e) {
                return response()->json([
                    'status'  => false,
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        public function removeFriendList($id)
        {
            try
            {
                $data = \App\Models\Following::where([
                    'user_id' => auth()->id(),
                    'following_id' => $id
                ])
                    ->orWhere([
                        'user_id' => $id,
                        'following_id' => auth()->id()
                    ])
                    ->delete();
                $friend = DB::table('friends')
                    ->where(['user_one_id'=>auth()->id(),'user_two_id'=>$id])
                    ->orWhere(['user_one_id'=>$id,'user_two_id'=>auth()->id()])
                    ->delete();
                return response()->json(['status'=>true, 'message'=>'Successfully remove the user from your friend list']);

            }catch (\Exception $e) {
                return response()->json([
                    'status'  => false,
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage()
                ], 500);
            }
        }
    }
