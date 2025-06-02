<?php

    namespace App\Http\Controllers\Api\V1\Account;

    use App\Http\Controllers\Controller;
    use App\Http\Resources\Api\V1\UserResource;
    use App\Models\Following;
    use App\Models\Post;
    use App\Models\PostPhoto;
    use App\Models\PostPhotoAlbum;
    use App\Models\PostView;
    use App\Models\User;
    use App\Models\UserInterest;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\UploadFileToStorage;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Validator;
    use Illuminate\Validation\Rule;

    class ProfileController extends Controller
    {
        use UniformResponseTrait, UploadFileToStorage;

        public function index()
        {
            $userId = auth('sanctum')->id();
            $user = User::withCount('followings', 'followers')->where('user_id', $userId)->first();

            if (!empty($user) && !empty($user->user_id)) {
                $user = UserResource::make($user) ?? NULL;

                return $this->sendResponse(TRUE, 'Profile data get successfully', $user);
            }

            return $this->sendResponse(FALSE, 'Something went wrong, Please try again.');
        }

        public function update(Request $request)
        {
            $userId = auth('sanctum')->id();

            $rules = [
                'user_firstname' => 'required',
                'user_lastname' => 'required',
                'user_phone' => [
                    'required',
                    'numeric',
                    'digits:10',
                    Rule::unique('users', 'user_phone')->ignore($userId, 'user_id')
                ],
                /*'email' => [
                    'email',
                    'max:255',
                    'regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                    Rule::unique('users', 'user_email')->ignore($userId, 'user_id')
                ],*/
            ];

            $validator = Validator::make($request->post(), $rules);
            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $requestData = array_filter($request->post());

            /*$requestData = [
                'user_firstname' => $request->post('user_firstname'),
                'user_lastname' => $request->post('user_lastname'),
                'user_phone' => $request->post('user_phone'),
                'user_email' => $request->post('user_email'),
                'user_latitude' => $request->post('user_latitude') ?? 0,
                'user_longitude' => $request->post('user_longitude') ?? 0,
                'user_location_updated' => $request->post('user_location_updated'),
                'user_birthdate' => $request->post('user_birthdate')
            ];*/

            $requestData['user_name'] = $requestData['user_firstname'] . ' ' . $requestData['user_lastname'];
            // $requestData['user_privacy_followers'] = !empty($requestData['user_privacy_followers']) && $requestData['user_privacy_followers'] == 'private' ? 'friends' : 'public';
            $requestData = array_filter($requestData);
            $requestData['user_last_seen'] = now()->format('Y-m-d H:i:s');
            $requestData['user_biography'] = $requestData['user_biography'] ?? null;

            if ($request->hasFile('user_picture')) {
                $image = $request->file('user_picture');
                $requestData['user_picture'] = $this->verifyAndUpload($image, time() . '.' . $image->getClientOriginalExtension(), 'users');
            }

            if ($request->hasFile('user_cover')) {
                $image = $request->file('user_cover');
                $requestData['user_cover'] = $this->verifyAndUpload($image, time() . '.' . $image->getClientOriginalExtension(), 'users');
            }

            $user = User::findOrFail($userId);
            $user->update($requestData);

            $user = UserResource::make($user);

            return $this->sendResponse(TRUE, 'Profile updated successfully.', $user);
        }

        public function destroy()
        {
            $userId = auth('sanctum')->id();

            $user = User::find($userId);

            if (!empty($user)) {
				Following::where('user_id', $userId)->delete();
				Following::where('following_id', $userId)->delete();
                DB::table('posts_comments')->where('user_id', $userId)->delete();
                DB::table('posts_comments_reactions')->where('user_id', $userId)->delete();
                DB::table('posts_reactions')->where('user_id', $userId)->delete();
				PostView::where('user_id', $userId)->delete();
                PostPhoto::whereRaw("album_id in (select album_id from posts_photos_albums where user_id = $userId)")->delete();
                PostPhotoAlbum::where('user_id', $userId)->delete();
                DB::table('posts_videos')->whereRaw("post_id in (select post_id from posts where user_id = $userId)")->delete();
				UserInterest::where('user_id', $userId)->delete();
				UserInterest::where('blocked_user_id', $userId)->delete();
                Post::where('user_id', $userId)->delete();

                // TODO:: Delete user data from related table
                $user->delete();

                return $this->sendResponse(TRUE, 'User account deleted successfully');
            }

            return $this->sendResponse(FALSE, 'Something went wrong, please try again.');
        }
    }
