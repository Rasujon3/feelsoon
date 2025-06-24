<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Http\Controllers\Controller;
    use App\Http\Resources\Api\V1\UserResource;
    use App\Models\Following;
    use App\Models\Notification;
    use App\Models\ReportCategory;
    use App\Models\User;
    use App\Models\UserBlock;
    use App\Models\UserInterest;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\SendPushNotificationTrait;
    use Exception;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Validator;

    class UserController extends Controller
    {
        use UniformResponseTrait;
        use SendPushNotificationTrait;

        public function index(Request $request)
        {
            try {
                $search = $request->post('search');
                $sortBy = $request->post('sort_by');
                $sortOrder = $request->post('sort_order');
                $type = $request->post('type', 'all'); // all, followings, followers, blocked, follow-request
                $latitude = $request->post('latitude', '0');
                $longitude = $request->post('longitude', '0');
                $userId = $request->post('userId', '0');
                $loginUserId = auth('sanctum')->id();
                $checkDistance = true;

                RECALL_QUERY:

                $users = User::selectRaw('*, (6371 * ACOS(
                    COS(RADIANS(' . $latitude . ')) * COS(RADIANS(user_latitude)) *
                    COS(RADIANS(user_longitude) - RADIANS(' . $longitude . ')) + SIN(RADIANS(' . $latitude . ')) * SIN(RADIANS(user_latitude))
                )) as distance,
                (select id from friends where user_one_id = '.$loginUserId.' and user_two_id = users.user_id limit 1) as is_requested,
                (select id from users_blocks where user_id = '.$loginUserId.' and blocked_id = users.user_id limit 1) as is_blocked
                ')
                    ->where('user_id', '!=', $loginUserId)
                    // ->where('user_verified', 1)
                    ->when(!empty($search), function ($q) use ($search) {
                        $q->where(function ($q1) use ($search) {
                            $q1->where('user_name', 'LIKE', "%$search%")
                                ->orWhere('user_firstname', 'LIKE', "%$search%")
                                ->orWhere('user_lastname', 'LIKE', "%$search%")
                                ->orWhere('user_biography', 'LIKE', "%$search%");
                        });
                    })
                    ->when(!empty($type) && $type != 'all', function ($q) use ($type, $loginUserId, $userId) {
                        if ($type == 'following') {
                            $q->whereRaw('user_id in (select following_id from followings where user_id = ' . (!empty($userId) ? $userId : $loginUserId) . ' and status = 1)');
                        }
                        else if ($type == 'followers') {
                            $q->whereRaw('user_id in (select user_id from followings where following_id = ' . (!empty($userId) ? $userId : $loginUserId) . ' and status = 1)');
                        }
                        else if ($type == 'blocked') {
                            $q->whereRaw('user_id in (select blocked_id from users_blocks where user_id = ' . $loginUserId . ')');
                        }
                        else if ($type == 'follow-request') {
                            $q->whereRaw('user_id in (select user_one_id from friends where user_two_id = ' . $loginUserId . ')');
                            $q->whereRaw('user_id not in (select following_id from followings where user_id = ' . (!empty($userId) ? $userId : $loginUserId) . ')');
                        }
                        else {
                            $q->whereRaw('user_id not in (select blocked_id from users_blocks where user_id = ' . $loginUserId . ')');
                        }
                    })
                    ->when(!empty($sortBy) && !empty($sortOrder), function ($q) use ($sortBy, $sortOrder) {
                        $q->orderBy($sortBy, $sortOrder);
                    })
                    ->when(empty($isLatest) && (empty($sortBy) || empty($sortOrder)), function ($q) {
                        $q->inRandomOrder();
                    })
                    ->when(!empty($latitude) && !empty($longitude) && $type == 'suggestion', function ($q) use ($loginUserId, $checkDistance) {
                        // Followings
                        $q->whereRaw('user_id not in (select following_id from followings where user_id = ' . $loginUserId . ')');
                        // Followers
                        // $q->whereRaw('user_id not in (select user_id from followings where following_id = ' . loginUserId . ')');
                        $q
                            //->having('distance', '<=', $checkDistance == true ? 100 : 1000)
                            ->orderBy('distance', 'asc');
                    });

                $users = $users->withCount('followings', 'followers')->paginate(20);
                $totalRecords = $users->total();
                if($totalRecords == 0 && $checkDistance == true) {
                    $checkDistance = false;
                    goto RECALL_QUERY;
                }
                $totalPages = $users->lastPage();
                $users = UserResource::collection($users->items());
                $currentPage = $request->post('page', 1);

                $response = [
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                    'current_page' => $currentPage,
                    'users' => !empty($users) ? $users : NULL,
                ];

                return $this->sendResponse(TRUE, 'Users data get successfully', $response);
            } catch(Exception $e) {
                // Log the error
                Log::error('Error in retrieving users: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }

        public function followUnfollowUser(Request $request)
        {
            $rules = [
                'user_id' => 'required|exists:users,user_id'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $followUserId = $request->post('user_id');
            $friendRequestId = $request->post('friend_request_id', '0');

            try {
                $checkUserPrivacy = User::find($followUserId);
                $loggedUser = User::find($loginUserId);

                if (!empty($checkUserPrivacy && !empty($loggedUser))) {
                    // Prevent users from following themselves
                    $message = 'You cannot follow yourself.';
                    if ($checkUserPrivacy->user_id === $loggedUser->user_id) {
                        return $this->sendResponse(false, $message, [], 422);
                    }

                    $followUser = Following::where('user_id', $loginUserId)
                        ->where('following_id', $followUserId)
                        ->first();

                    $message = 'Following the user successfully';
                    if (!empty($followUser) && !empty($followUser->id)) {
                        $followUser->delete();
                        $message = 'Unfollow the user successfully';
                    }
                    else {
                        $status = ($checkUserPrivacy->user_privacy_followers === 'public' && $loggedUser->user_privacy_followers === 'public') ? 1 : 0;
                        $message = $status === 0 ? 'Follow request sent successfully' : 'Following the user successfully';

                        Following::create([
                            'user_id' => $loginUserId,
                            'following_id' => $followUserId,
                            'status' => $status,
                            'time' => now()->format('Y-m-d H:i:s')
                        ]);

                        Notification::create([
                            'to_user_id' => $followUserId,
                            'from_user_id' => $loginUserId,
                            'from_user_type' => 'user',
                            'node_type' => 'user',
                            'node_url' => $loginUserId,
                            'action' => 'follow',
                            'message' => $status === 1 ? 'started following you' : 'want to follow you',
                            'time' => now()->format('Y-m-d H:i:s')
                        ]);

                        if(!empty($checkUserPrivacy->device_token)) {
                            $pushMessageFormat = $status === 1 ? ' started following you.' : ' want to follow you';
                            $pushMessage = auth('sanctum')->user()->first_name . $pushMessageFormat;
                            $pushData = [
                                'user_id' => $loginUserId
                            ];
                            $this->sendNotification($checkUserPrivacy->device_token. 'Following', $pushMessage, $pushData);
                        }
                    }
                }

                return $this->sendResponse(TRUE, $message);
            } catch(Exception $e) {
                // Log the error
                Log::error('Error in follow unfollow users: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }

        public function followUnfollowUserBK(Request $request)
        {
            $rules = [
                'user_id' => 'required|exists:users,user_id'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $followUserId = $request->post('user_id');
            $friendRequestId = $request->post('friend_request_id', '0');

            $checkUserPrivacy = User::find($followUserId);
            if (!empty($checkUserPrivacy)) {
                if ($checkUserPrivacy->user_privacy_followers == 'friends' && empty($friendRequestId)) {
                    $followRequest = DB::table('friends')->where('user_one_id', $loginUserId)->where('user_two_id', $followUserId)->first();

                    $message = 'Follow request sent successfully';
                    if (!empty($followRequest) && !empty($followRequest->id)) {
                        DB::table('friends')->where('user_one_id', $loginUserId)->where('user_two_id', $followUserId)->delete();
                        $message = 'Follow request removed successfully.';
                    }
                    else {
                        DB::table('friends')->insert([
                            'user_one_id' => $loginUserId,
                            'user_two_id' => $followUserId,
                            'status' => 0
                        ]);

                        Notification::create([
                            'to_user_id' => $followUserId,
                            'from_user_id' => $loginUserId,
                            'from_user_type' => 'user',
                            'node_type' => 'user',
                            'node_url' => $loginUserId,
                            'action' => 'follow',
                            'message' => 'want to follow you',
                            'time' => now()->format('Y-m-d H:i:s')
                        ]);

                        if(!empty($checkUserPrivacy->device_token)) {
                            $pushMessage = auth('sanctum')->user()->first_name . ' want to follow you.';
                            $pushData = [
                                'user_id' => $loginUserId
                            ];
                            $this->sendNotification($checkUserPrivacy->device_token. 'Follow Request', $pushMessage, $pushData);
                        }
                    }
                }
                else {
                    $followUser = Following::where('user_id', $loginUserId)->where('following_id', $followUserId)->first();

                    $message = 'Following the user successfully';
                    if (!empty($followUser) && !empty($followUser->id)) {
                        $followUser->delete();
                        $message = 'Unfollow the user successfully';
                    }
                    else {
                        Following::create([
                            'user_id' => $loginUserId,
                            'following_id' => $followUserId
                        ]);

                        Notification::create([
                            'to_user_id' => $followUserId,
                            'from_user_id' => $loginUserId,
                            'from_user_type' => 'user',
                            'node_type' => 'user',
                            'node_url' => $loginUserId,
                            'action' => 'follow',
                            'message' => 'started following you',
                            'time' => now()->format('Y-m-d H:i:s')
                        ]);

                        if(!empty($checkUserPrivacy->device_token)) {
                            $pushMessage = auth('sanctum')->user()->first_name . ' started following you.';
                            $pushData = [
                                'user_id' => $loginUserId
                            ];
                            $this->sendNotification($checkUserPrivacy->device_token. 'Following', $pushMessage, $pushData);
                        }
                    }
                }
            }

            return $this->sendResponse(TRUE, $message);
        }

        public function blockUnblockUser(Request $request)
        {
            $rules = [
                'user_id' => 'required|exists:users,user_id'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $blockUserId = $request->post('user_id');

            $blockUser = UserBlock::where('user_id', $loginUserId)->where('blocked_id', $blockUserId)->first();

            $message = 'User blocked successfully';
            if (!empty($blockUser) && !empty($blockUser->id)) {
                $blockUser->delete();
                $message = 'User removed from blocked list successfully.';
            }
            else {
                UserBlock::create([
                    'user_id' => $loginUserId,
                    'blocked_id' => $blockUserId
                ]);
            }

            return $this->sendResponse(TRUE, $message);
        }

        public function show($userId, Request $request)
        {
            $latitude = $request->post('latitude', '0');
            $longitude = $request->post('longitude', '0');

            $user = User::selectRaw('*, (6371 * ACOS(
                    COS(RADIANS(' . $latitude . ')) * COS(RADIANS(user_latitude)) *
                    COS(RADIANS(user_longitude) - RADIANS(' . $longitude . ')) + SIN(RADIANS(' . $latitude . ')) * SIN(RADIANS(user_latitude))
                )) as distance,
                (select id from friends where user_one_id = '.(auth('sanctum')->id() ?? 0).' and user_two_id = users.user_id limit 1) as is_requested');

            $user = $user->withCount('followings', 'followers')->find($userId);
            $user = UserResource::make($user);

            return $this->sendResponse(TRUE, 'Users data get successfully', $user);
        }

        public function acceptRejectFriend(Request $request)
        {
            try
            {
            	$rules = [
                    'user_id' => 'required|exists:users,user_id',
                    'action' => 'required|in:1,2',
                ];

                $validator = Validator::make($request->post(), $rules);

                if ($validator->fails()) {
                    $error = $validator->errors()->all(':message');
					DB::commit();
                    return $this->sendResponse(FALSE, $error[0]);
                }

                $loginUserId = auth('sanctum')->id();
                $requestUserId = $request->post('user_id');
                $action = $request->post('action'); // 1-Accept, 2-Reject

                //$followRequest = DB::table('friends')->where('user_one_id', $requestUserId)->where('user_two_id', $loginUserId)->first();
                $followRequest = DB::table('followings')
                                 ->where('user_id',$requestUserId)
                                 ->where('following_id',$loginUserId)
                                 //->where('status',0)
                                 ->first();
                //return $followRequest;
                $message = 'Request not found';
                if(!empty($followRequest)) {
                    if($action == 1) {
                        $message = 'Request accepted succcessfully';
                        //Following::create([
                            //'user_id' => $requestUserId,
                            //'following_id' => $loginUserId
                        //]);

                        DB::table('followings')
                                 ->where('user_id',$requestUserId)
                                 ->where('following_id',$loginUserId)
                                 //->where('status',0)
                                 ->update(['status'=>1]);
                        $count = DB::table('friends')->where('user_one_id', $requestUserId)->where('user_two_id', $loginUserId)->count();
                        if($count > 0)
                        {
                        	DB::table('friends')->where('user_one_id', $requestUserId)->where('user_two_id', $loginUserId)->delete();
                        }

                        $data = array();
                        $data['user_one_id'] = $requestUserId;
                        $data['user_two_id'] = $loginUserId;
                        $data['status'] = 1;
                        DB::table('friends')->insert($data);

                        Notification::create([
                            'to_user_id' => $loginUserId,
                            'from_user_id' => $requestUserId,
                            'from_user_type' => 'user',
                            'node_type' => 'user',
                            'node_url' => $requestUserId,
                            'action' => 'follow',
                            'message' => 'accepted your follow request',
                            'time' => now()->format('Y-m-d H:i:s')
                        ]);
                    }
                    else {
                        DB::table('friends')->where('user_one_id', $requestUserId)->where('user_two_id', $loginUserId)->delete();
                        DB::table('followings')
                                 ->where('user_id',$requestUserId)
                                 ->where('following_id',$loginUserId)
                                 ->delete();
                        $message = 'Request removed successfully';
                    }
                }

                //DB::table('friends')->where('user_one_id', $requestUserId)->where('user_two_id', $loginUserId)->delete();
				DB::commit();
                return $this->sendResponse(!empty($followRequest), $message);
            }catch(Exception $e){
                DB::rollback();
            	return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
            }

        }

        public function saveUserInterests(Request $request)
        {
            $rules = [
                'user_id' => 'required|exists:users,user_id'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $userId = $request->post('user_id');

            $ignoreUser = UserInterest::where('user_id', $loginUserId)->where('blocked_user_id', $userId)->first();

            $message = 'Record added successfully to not interested list';
            if (!empty($ignoreUser) && !empty($ignoreUser->id)) {
                $ignoreUser->delete();
                $message = 'Record removed from not interests list.';
            }
            else {
                UserInterest::create([
                    'user_id' => $loginUserId,
                    'blocked_user_id' => $userId
                ]);
            }

            return $this->sendResponse(TRUE, $message);
        }

        public function reportCategories(): JsonResponse
        {
            DB::enableQueryLog();
            // $categories = DB::table('reports_categories')->orderBy('category_order')->where('category_parent_id', 0)->get();
            $categories = ReportCategory::orderBy('category_order')->whereNull('category_parent_id')->with('children')->get();

            return $this->sendResponse(TRUE, 'Categories data get successfully', $categories);
        }

        public function reportUser(Request $request)
        {
            $rules = [
                'user_id' => 'required|exists:users,user_id',
                'category_id' => 'required|exists:reports_categories,category_id',
                'reason' => 'required'
            ];

            $validator = Validator::make($request->post(), $rules);

            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $userId = $request->post('user_id');
            $categoryId = $request->post('category_id');
            $reason = $request->post('reason');

            $isReported = DB::table('reports')->where('user_id', $loginUserId)
                ->where('node_id', $userId)
                ->where('node_type', 'user')
                ->first();

            $message = 'User reported successfully';
            if (empty($isReported)) {
                DB::table('reports')->insert([
                    'user_id' => $loginUserId,
                    'node_id' => $userId,
                    'node_type' => 'user',
                    'category_id' => $categoryId,
                    'reason' => $reason,
                    'time' => now()->format('Y-m-d H:i:s')
                ]);
            }

            return $this->sendResponse(TRUE, $message);
        }
    }
