<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Http\Controllers\Api\BaseController;
    use App\Models\Post;
    use App\Models\PostShare;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Validator;
    use Illuminate\Support\Facades\DB;

    class ShareController extends BaseController
    {
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
            $shares = DB::table('posts_shares')
                ->selectRaw('id, post_id, posts_shares.user_id, user_name, user_firstname, user_lastname, user_picture, user_latitude, user_longitude')
                ->where('post_id', $postId)
                ->join('users', 'posts_shares.user_id', 'users.user_id')
                ->paginate(20);

            $totalRecords = $shares->total();
            $totalPages = $shares->lastPage();
            $shares = $shares->items();
            $currentPage = $request->post('page', 1);

            $response = [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'users' => !empty($shares) ? $shares : NULL,
            ];

            return $this->sendResponse($totalRecords > 0, 'Posts data get successfully', $response);
        }

        public function store(Request $request)
        {
            $rules = [
                'post_ids' => 'required'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $postIds = explode(',', $request->post('post_ids'));

            foreach ($postIds as $postId) {
                PostShare::firstOrCreate([
                    'post_id' => $postId,
                    'user_id' => $loginUserId
                ], [
                    'share_time' => now()->format("Y-m-d H:i:s")
                ]);
                Post::where('post_id', $postId)->increment('shares');
            }

            $message = 'Post shared successfully.';

            return $this->sendResponse(TRUE, $message);
        }
    }
