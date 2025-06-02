<?php

    namespace App\Traits;

    use Illuminate\Support\Facades\Storage;

    trait UploadFileToStorage
    {
        /*public function uploadFileAws($folderPath, $file)
        {
            try {
                // $fileName = time() . '.' . $file->getClientOriginalExtension();
                // $path = Storage::disk('s3')->put($folderPath, $file);
                // $url = Storage::disk('s3')->url($path);

                // if (isset($url) && !empty($url)) {
                // 	return ['status' => TRUE, 'url' => $url];
                // }

                // File::isDirectory($path) or File::makeDirectory($path, 0777, TRUE, TRUE);
                $originalName = str_replace(' ', '_', $file->getClientOriginalName());
                $fileName = $originalName . '_' . time() . '.' . $file->getClientOriginalExtension();

                $url = $file->storeAs($folderPath, $fileName);

                // dd($url);
                if (isset($url) && !empty($url)) {
                    return ['status' => TRUE, 'url' => 'storage/' . $url];
                }
            }
            catch (\Exception $e) {
                info('AWS file upload error: ' . $e->getMessage());
            }

            return ['status' => FALSE];
        }*/

        public function verifyAndUpload($file, $fileName, $directory, $isM3u8=false): array|string
        {
            // Upload to Folder Storage
            // $filePath = $file->storeAs($directory, $fileName);
            // return 'storage/' . $filePath;

            // Upload to S3
            try {
                if($isM3u8 == true) {
                    $path = Storage::disk('s3')->putFileAs($directory, $file, $fileName, [
                        'Content-Type' => 'application/vnd.apple.mpegurl'
                    ]);
                }
                else {
                    $path = Storage::disk('s3')->putFile($directory, $file);
                }
                $url = Storage::disk('s3')->url($path);

            }
            catch(\Exception $e) {
                info($e->getMessage());
            }


            if (isset($url) && !empty($url)) {
                return $url;
            }

            if (!$file->isValid()) {
                info("Error: Invalid file, S3");
                // return ['error' => 'Invalid file!'];
            }

            return FALSE;
        }
    }
