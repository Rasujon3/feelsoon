<?php

    /*use App\Http\Controllers\ProfileController;*/
    use Illuminate\Support\Facades\Route;
    use Illuminate\Http\Request;

    /*Route::get('/', function () {
        return view('welcome');
    });

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });*/

    // require __DIR__ . '/auth.php';

    Route::any('/spotify/callback', function (Request $request) {
        dd($request->all());
    });

    Route::any('/spotify/token', function (Request $request) {
        dd($request->all());
    });

    Route::get('/firebase/token', function () {
        // Path to Firebase service account JSON
        $serviceAccountPath = __DIR__ . '/../firebase-service-account.json';

        // Define OAuth scope for Firebase Cloud Messaging
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        // Load credentials
        $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials($scopes, $serviceAccountPath);

        // Fetch the OAuth 2.0 access token
        $token = $credentials->fetchAuthToken();

        return $token['access_token'];
    });

    Route::get('/firebase/test', function () {
        $user = \App\Models\User::find(request()->input('id'));
        $res = \App\Traits\SendPushNotificationTrait::sendNotificationStatic($user->device_token, 'Test', 'This is test notification');
        dd($res);
    });
