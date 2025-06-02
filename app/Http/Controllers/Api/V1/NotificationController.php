<?php

    namespace App\Http\Controllers\Api\V1;

    use App\Http\Controllers\Api\BaseController;
    use App\Http\Resources\Api\V1\Modules\NotificationResource;
    use App\Http\Resources\Api\V1\Modules\PostResource;
    use App\Models\Notification;
    use Illuminate\Http\Request;

    class NotificationController extends BaseController
    {
        public function index(Request $request)
        {
            $loginUserId = auth('sanctum')->id();

            $notifications = Notification::selectRaw("*,
            (select following_id from followings where user_id = $loginUserId and following_id = notifications.from_user_id) as is_following,
            (select user_two_id from friends where user_one_id = $loginUserId and user_two_id = notifications.from_user_id) as is_requested,
            (select blocked_id from users_blocks where user_id = $loginUserId and blocked_id = notifications.from_user_id) as is_blocked
            ")->where('to_user_id', $loginUserId);

            $notifications = $notifications->paginate($this->pagination);
            $totalRecords = $notifications->total();
            $totalPages = $notifications->lastPage();
            $notifications = NotificationResource::collection($notifications->items());
            $currentPage = $request->post('page', 1);

            $response = [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $currentPage,
                'notifications' => !empty($notifications) ? $notifications : NULL,
            ];

            return $this->sendResponse($totalRecords > 0 ? TRUE : FALSE, 'Notifications data get successfully', $response);
        }
    }
