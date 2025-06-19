<?php

    use App\Http\Controllers\Api\V1\Account\ProfileController;
    use App\Http\Controllers\Api\V1\Auth\LoginController;
    use App\Http\Controllers\Api\V1\MasterController;
    use App\Http\Controllers\Api\V1\Module\ChatController;
    use App\Http\Controllers\Api\V1\Module\CommentController;
    use App\Http\Controllers\Api\V1\Module\LikeController;
    use App\Http\Controllers\Api\V1\Module\MusicController;
    use App\Http\Controllers\Api\V1\Module\PostController;
    use App\Http\Controllers\Api\V1\Module\ShareController;
    use App\Http\Controllers\Api\V1\Module\UserController;
    use App\Http\Controllers\Api\V1\NotificationController;
    use Illuminate\Support\Facades\Route;

    Route::post('/', function (\Illuminate\Http\Request $request) {

        $videoFile = $request->file('video');
        $originalName = pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $outputFolder = "hls_videos/$originalName";

        // Store uploaded file temporarily
        $inputPath = $videoFile->storeAs('temp_videos', $videoFile->getClientOriginalName(), 'local');

        // Convert to HLS format
        $hlsPath = \App\Services\HLSConverter::convertToHLS(storage_path("app/$inputPath"), $outputFolder);

        // Upload HLS files to S3
        foreach (scandir($hlsPath) as $file) {
            if ($file !== '.' && $file !== '..') {
                \Illuminate\Support\Facades\Storage::disk('s3')->put("$outputFolder/$file", file_get_contents("$hlsPath/$file"));
            }
        }

        // Generate S3 URL for .m3u8 file
        $playlistUrl = \Illuminate\Support\Facades\Storage::disk('s3')->url("$outputFolder/output.m3u8");

        return response()->json([
            'message' => 'Video converted to HLS and uploaded to S3 successfully!',
            'video_url' => $playlistUrl
        ]);
    });

    Route::prefix('v1')->name('v1.')->group(function () {
        Route::prefix('masters')->name('masters.')->group(function () {
            Route::post('languages', [MasterController::class, 'languages'])->name('languages');
            Route::post('countries', [MasterController::class, 'countries'])->name('countries');
            Route::post('genders', [MasterController::class, 'genders'])->name('countries');
            Route::post('interests', [MasterController::class, 'interests'])->name('interests');
        });

        Route::prefix('musics')->name('musics.')->group(function () {
            Route::get('/', [MusicController::class, 'index'])->name('index');
            Route::post('/', [MusicController::class, 'store'])->name('store');
            Route::get('music/{filename}', [MusicController::class, 'getMusic'])->name('getMusic');
            Route::get('/{id}', [MusicController::class, 'show'])->name('show');
            Route::post('/update', [MusicController::class, 'update'])->name('update');
            Route::post('/destroy', [MusicController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('login', [LoginController::class, 'login'])->name('login');
            Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth:sanctum');
        });

        Route::middleware('auth:sanctum')->group(function () {

            Route::post('my-suggessions', [PostController::class, 'mySuggessions']);
            Route::post('my-friend-lists', [PostController::class, 'myFriendLists']);
            Route::get('/remove-friend-list/{id}', [PostController::class, 'removeFriendList']);
            Route::post('trash-suggestion', [PostController::class, 'trashSuggestion']);
            Route::post('my-request-list', [PostController::class, 'myFollowers']);

            Route::prefix('account')->name('account.')->group(function () {
                Route::post('profile', [ProfileController::class, 'index'])->name('profile');
                Route::post('profile/update', [ProfileController::class, 'update'])->name('profile.update');
                Route::post('destroy', [ProfileController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('users')->name('users.')->group(function () {
                Route::post('/', [UserController::class, 'index'])->name('index'); // type = all,following,followers,blocked
                Route::post('/follow', [UserController::class, 'followUnfollowUser'])->name('follow-unfollow');
                Route::post('/follow/request-action', [UserController::class, 'acceptRejectFriend'])->name('friend-request');
                Route::post('/block', [UserController::class, 'blockUnblockUser'])->name('block-unblock');
                Route::post('/interests', [UserController::class, 'saveUserInterests'])->name('interests');
                Route::prefix('reports')->name('reports.')->group(function () {
                    Route::post('/categories', [UserController::class, 'reportCategories'])->name('categories');
                    Route::post('/store', [UserController::class, 'reportUser'])->name('user');
                });
                Route::post('/{id}', [UserController::class, 'show'])->name('show');
            });

            Route::name('modules.')->group(function () {
                Route::prefix('posts')->name('posts.')->group(function () {
                    Route::post('/', [PostController::class, 'index'])->name('index');
                    Route::post('/categories', [PostController::class, 'categories'])->name('categories');
                    Route::post('/store', [PostController::class, 'store'])->name('store');
                    Route::post('/update', [PostController::class, 'update'])->name('update');
                    Route::post('/destroy', [PostController::class, 'destroy'])->name('destroy');
                    // Route::post('/store', [PostController::class, 'store'])->name('store');

                    Route::prefix('likes')->name('likes.')->group(function () {
                        Route::post('/', [LikeController::class, 'index'])->name('index');
                        Route::post('store', [LikeController::class, 'store'])->name('store');
                    });

                    Route::prefix('comments')->name('comments.')->group(function () {
                        Route::post('/', [CommentController::class, 'index'])->name('index');
                        Route::post('store', [CommentController::class, 'store'])->name('store');
                        Route::post('like', [CommentController::class, 'storeLike'])->name('like');
                    });

                    Route::prefix('shares')->name('shares.')->group(function () {
                        Route::post('/', [ShareController::class, 'index'])->name('index');
                        Route::post('store', [ShareController::class, 'store'])->name('store');
                    });

                    Route::prefix('views')->name('views.')->group(function () {
                        Route::post('/', [PostController::class, 'views'])->name('views');
                        Route::post('store', [PostController::class, 'storeViews'])->name('store');
                    });

                    Route::post('/{id}', [PostController::class, 'show'])->name('show');
                });

                Route::prefix('chats')->name('chats.')->group(function () {
                    Route::post('/', [ChatController::class, 'index'])->name('index');
                    Route::post('/store', [ChatController::class, 'store'])->name('store');
                    Route::post('/messages', [ChatController::class, 'messages'])->name('messages');
                });
            });

            Route::prefix('notifications')->name('notifications.')->group(function () {
                Route::post('/', [NotificationController::class, 'index'])->name('index');
                // Route::post('destroy', [NotificationController::class, 'destroy']);
            });
        });
    });
