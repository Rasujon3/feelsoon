<?php

    namespace App\Http\Controllers\Api\V1\Auth;

    use App\Http\Controllers\Api\BaseController;
    use App\Http\Resources\Api\V1\UserResource;
    use App\Models\User;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\UploadFileToStorage;
    use Exception;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Validator;
    use Illuminate\Support\Str;

    class LoginController extends BaseController
    {
        use UniformResponseTrait, UploadFileToStorage;

        public function login(Request $request): JsonResponse
        {
            try {
                if (!empty($request->post('user_interests'))) {
                    $interestsRaw = $request->input('user_interests');
                    $interests = array_map('intval', explode(',', str_replace(['[', ']'], '', $interestsRaw)));

                    $request->merge([
                        'user_interests' => $interests
                    ]);
                }
            	$rules = [
                    'user_phone' => 'required|numeric|digits:10',
                    'device_type' => 'required|in:1,2', // 1=android, 2=ios
                    'device_token' => 'required',
                    'user_latitude' => 'required',
                    'user_longitude' => 'required',
                    'user_country' => ['nullable', 'integer', 'exists:system_countries,country_id'],
                    'user_language' => ['nullable', 'integer', 'exists:system_languages,language_id'],
                    'user_firstname' => ['nullable', 'string', 'max:256'],
                    'user_lastname' => ['nullable', 'string', 'max:256'],
                    'user_birthdate' => ['nullable', 'date'],
                    'user_gender' => ['nullable', 'integer', 'exists:system_genders,gender_id'],
                    'user_picture' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
                    'user_cover' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
                    'user_interests'   => 'required|array',
                    'user_interests.*' => 'exists:pages_categories,category_id',
              ];

              $validator = Validator::make($request->post(), $rules);
              if ($validator->fails()) {
                  $error = $validator->errors()->all(':message');

                  return $this->sendResponse(FALSE, $error[0]);
              }

              $mobileNumber = $request->post('user_phone');

              $user = User::where('user_phone', $mobileNumber)->withCount('followings', 'followers')->first();

              if (empty($user)) {
                  if (empty($request->post('user_firstname'))) {
                      return $this->sendResponse(TRUE, 'Please continue for user registration', NULL, 201);
                  }

                  $rules = [
                      'user_firstname' => 'required',
                      'user_lastname' => 'required',
                      // 'user_email' => 'required|email',
                      'user_birthdate' => 'required',
                      'user_gender' => 'required',
                      'user_country' => 'required',
                      'user_language' => 'required',
                      'user_interests' => 'required'
                  ];

                  $validator = Validator::make($request->post(), $rules);
                  if ($validator->fails()) {
                      $error = $validator->errors()->all(':message');

                      return $this->sendResponse(FALSE, $error[0]);
                  }
                  $requestData = $request->post();
                  $requestData['user_name'] = $requestData['user_firstname'] . ' ' . $requestData['user_lastname'];
                  $requestData['user_password'] = bcrypt(Str::random('8'));
                  $requestData['user_approved'] = 1;
                  $requestData['user_registered'] = now()->format("Y-m-d H:i:s");
                  $requestData['user_last_seen'] = now()->format('Y-m-d H:i:s');
                  // $requestData['user_privacy_followers'] = !empty($requestData['user_privacy_followers']) && $requestData['user_privacy_followers'] == 'private' ? 'friends' : 'public';

                  if ($request->hasFile('user_picture')) {
                      $image = $request->file('user_picture');
                      $requestData['user_picture'] = $this->verifyAndUpload($image, time() . '.' . $image->getClientOriginalExtension(), 'users');
                  }

                  if ($request->hasFile('user_cover')) {
                      $image = $request->file('user_cover');
                      $requestData['user_cover'] = $this->verifyAndUpload($image, time() . '.' . $image->getClientOriginalExtension(), 'users');
                  }

                  User::create($requestData);
                  $user = User::where('user_phone', $mobileNumber)->withCount('followings', 'followers')->first();
              }

              $userPicture = $user->user_picture;
              $userCover = $user->user_cover;

              if (!empty($user) && !empty($user->user_id)) {
                  if ($request->hasFile('user_picture')) {
                      $image = $request->file('user_picture');
                      $userPicture = $this->verifyAndUpload($image, time() . '.' . $image->getClientOriginalExtension(), 'users');
                  }

                  if ($request->hasFile('user_cover')) {
                      $image = $request->file('user_cover');
                      $userCover = $this->verifyAndUpload($image, time() . '.' . $image->getClientOriginalExtension(), 'users');
                  }

                  $user->update([
                      'device_type' => $request->post('device_type'),
                      'device_token' => $request->post('device_token'),
                      'user_latitude' => $request->post('user_latitude'),
                      'user_longitude' => $request->post('user_longitude'),
                      'user_country' => $request->get('user_country') ?: $user->user_country,
                      'user_language' => $request->get('user_language') ?: $user->user_language,
                      'user_firstname' => $request->get('user_firstname') ?: $user->user_firstname,
                      'user_lastname' => $request->get('user_lastname') ?: $user->user_lastname,
                      'user_birthdate' => $request->get('user_birthdate') ?: $user->user_birthdate,
                      'user_gender' => $request->get('user_gender') ?: $user->user_gender,
                      'user_picture' => $userPicture,
                      'user_cover' => $userCover,
                      'user_interests' => $request->get('user_interests') ?: $user->user_interests,
                  ]);

                  $token = $user->createToken(md5($user->user_phone . $user->user_name . time()));
                  $user->token = $token->plainTextToken;

                  $user = UserResource::make($user) ?? NULL;

                  return $this->sendResponse(TRUE, 'Login Successfully', $user);
              }
            } catch(Exception $e){
                // Log the error
                Log::error('Error in Login: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            	return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
            }
            return response()->json(['status' => false, 'message' => 'Something went wrong!!!'],500);
        }

        public function logout(Request $request)
        {
            auth('sanctum')->user()->tokens()->delete();

            return $this->sendResponse(TRUE, 'Logout Successfully');
        }
    }
