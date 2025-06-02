<?php

    namespace App\Http\Controllers\Api\V1\Auth;

    use App\Http\Controllers\Api\BaseController;
    use App\Http\Resources\Api\V1\UserResource;
    use App\Models\User;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\UploadFileToStorage;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Validator;
    use Illuminate\Support\Str;

    class LoginController extends BaseController
    {
        use UniformResponseTrait, UploadFileToStorage;

        public function login(Request $request): JsonResponse
        {
            try{
            	$rules = [
                  'user_phone' => 'required|numeric|digits:10',
                  'device_type' => 'required|in:1,2', // 1=android, 2=ios
                  'device_token' => 'required',
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

              $user->update([
                  'device_type' => $request->post('device_type'),
                  'device_token' => $request->post('device_token'),
                  'user_latitude' => $request->post('user_latitude'),
                  'user_longitude' => $request->post('user_longitude'),
              ]);

              $token = $user->createToken(md5($user->user_phone . $user->user_name . time()));
              $user->token = $token->plainTextToken;

              $user = UserResource::make($user) ?? NULL;

              return $this->sendResponse(TRUE, 'Login Successfully', $user);
              
           
              
            }catch(Exception $e){
                \Log::error($e->getMessage());
            	return response()->json(['status'=>false, 'code'=>$e->getCode(), 'message'=>$e->getMessage()],500);
            }
        }

        public function logout(Request $request)
        {
            auth('sanctum')->user()->tokens()->delete();

            return $this->sendResponse(TRUE, 'Logout Successfully');
        }
    }
