<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Http\Controllers\Controller;
    use App\Models\Music;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\UploadFileToStorage;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Validator;
    use Illuminate\Support\Facades\Log;
    use Exception;

    class MusicController extends Controller
    {
        use UniformResponseTrait, UploadFileToStorage;

        public function index(Request $request): JsonResponse
        {
            try {
                $query = Music::withCount('posts')
                    ->when($request->filled('title'), function ($q) use ($request) {
                        $q->where('title', 'like', '%' . $request->title . '%');
                    })
                    ->when($request->filled('singer_name'), function ($q) use ($request) {
                        $q->where('singer_name', 'like', '%' . $request->singer_name . '%');
                    });

                $response = [
                    'data'  => $query->get(),
                ];

                return $this->sendResponse(true, 'Musics retrieved successfully.', $response);
            } catch (Exception $e) {

                // Log the error
                Log::error('Error in retrieving Music: ' , [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }

        public function store(Request $request): JsonResponse
        {
            try {
                $rules = [
                    'user_id'     => 'nullable|exists:users,user_id',
                    'title'       => 'required|string|max:100',
                    'singer_name' => 'nullable|string|max:100',
                    'file'        => 'nullable|mimetypes:audio/mpeg,audio/mp3|max:5120',
                ];

                $validator = Validator::make($request->all(), $rules);

                if ($validator->fails()) {
                    $error = $validator->errors()->all(':message');
                    return $this->sendResponse(false, $error[0]);
                }

                $requestData = $request->all();
                $requestData['user_id'] = $requestData['user_id'] ?? null;
                $requestData['title'] = $requestData['title'] ?? '';
                $requestData['singer_name'] = $requestData['singer_name'] ?? '';
                $requestData['file_path'] = null;
                $requestData['created_at'] = now();
                $requestData['updated_at'] = now();

                // Handle file upload
                if ($request->hasFile('file')) {
                    $filePath = $this->storeFile($request->file('file'));
                    $requestData['file_path'] = $filePath ?? '';
                }

                $music = Music::create($requestData);

                return $this->sendResponse(true, 'Music created successfully.', $music);

            } catch (Exception $e) {

                // Log the error
                Log::error('Error in creating Music: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }

        public function getMusic($filename)
        {
            try {
                $path = public_path('files/music/mp3/' . $filename);

                if (!file_exists($path)) {
                    return $this->sendResponse(false, '404, File not found.', []);
                }

                return response()->file($path);
            } catch (Exception $e) {

                // Log the error
                Log::error('Error in get Music: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }

        public function show($musicId)
        {
            try {
                $music = Music::find($musicId);

                $message = 'Music not found';
                if (!empty($music)) {
                    $message = 'Music data get successfully';
                }

                return $this->sendResponse((!empty($music)) > 0 ? true : false, $message, $music);
            } catch (Exception $e) {

                // Log the error
                Log::error('Error in retrieving Music: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }

        public function update(Request $request): JsonResponse
        {
            try {
                $rules = [
                    'music_id'    => 'required|exists:musics,id',
                    'title'       => 'required|string|max:100',
                    'singer_name' => 'nullable|string|max:100',
                    'file'        => 'nullable|mimetypes:audio/mpeg,audio/mp3|max:5120',
                ];

                $validator = Validator::make($request->all(), $rules);
                if ($validator->fails()) {
                    $error = $validator->errors()->all(':message');

                    return $this->sendResponse(false, $error[0]);
                }

                $musicId = $request->input('music_id');


                $music = Music::find($musicId);

                $requestData = $request->except(['music_id', 'file']);
                $requestData['user_id'] = $requestData['user_id'] ?? $music->user_id;
                $requestData['title'] = $requestData['title'] ?? $music->title;
                $requestData['singer_name'] = $requestData['singer_name'] ?? $music->singer_name;
                $requestData['file_path'] = $music->file_path;
                $requestData['updated_at'] = now();

                // Handle file upload
                if ($request->hasFile('file')) {
                        $filePath = $this->updateFile($request->file('file'), $music);
                        $requestData['file_path'] = $filePath ?? '';
                    }

                Music::where('id', $musicId)->update($requestData);

                $music = Music::find($musicId);

                return $this->sendResponse(true, 'Music updated successfully.', $music);
            } catch (Exception $e) {

                // Log the error
                Log::error('Error in updating Music: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return $this->sendResponse(false, 'Something went wrong!!!', [],500);
            }
        }

        public function destroy(Request $request)
        {
            try {
                $rules = [
                    'music_id'  => 'required|exists:musics,id',
                ];

                $validator = Validator::make($request->post(), $rules);
                if ($validator->fails()) {
                    $error = $validator->errors()->all(':message');

                    return $this->sendResponse(false, $error[0]);
                }

                $musicId = $request->input('music_id');
                $music = Music::find($musicId);

                $deleteFile = $this->deleteOldFile($music);

                $music->delete();

                return $this->sendResponse(true, 'Music deleted successfully.', []);

            } catch (Exception $e) {

                // Log the error
                Log::error('Error in deleting Music: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }

        private function storeFile($file)
        {
            // Define the directory path
            $filePath = 'files/music/mp3';
            $directory = public_path($filePath);

            // Ensure the directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            // Generate a unique file name
            $fileName = uniqid('music_', true) . '.' . $file->getClientOriginalExtension();

            // Move the file to the destination directory
            $file->move($directory, $fileName);

            // path & file name in the database
            # $path = $filePath . '/' . $fileName;
            $path = $fileName;
            return $path;
        }

        private function updateFile($file, $data)
        {
            // Define the directory path
            $filePath = 'files/music/mp3';
            $directory = public_path($filePath);

            // Ensure the directory exists
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            // Generate a unique file name
            $fileName = uniqid('music_', true) . '.' . $file->getClientOriginalExtension();

            // Delete the old file if it exists
            $this->deleteOldFile($data);

            // Move the new file to the destination directory
            $file->move($directory, $fileName);

            // Store path & file name in the database
            # $path = $filePath . '/' . $fileName;
            $path = $fileName;
            return $path;
        }
        private function deleteOldFile($data)
        {
            if (!empty($data->file_path)) {
                $filePath = 'files/music/mp3';
                $directory = $data->file_path;
                $path = $filePath . '/' . $directory;

                $oldFilePath = public_path($path); // Use without prepending $filePath
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath); // Delete the old file
                    return true;
                } else {
                    Log::warning('Old file not found for deletion', ['path' => $oldFilePath]);
                    return false;
                }
            }
        }
    }
