<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Http\Controllers\Controller;
    use App\Models\Music;
    use App\Models\User;
    use App\Traits\Api\UniformResponseTrait;
    use App\Traits\UploadFileToStorage;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\Validator;
    use Illuminate\Support\Facades\Log;
    use Exception;

    class LocationController extends Controller
    {
        use UniformResponseTrait, UploadFileToStorage;

        public function getLocation(Request $request)
        {
            try {
                $loginUserId = auth('sanctum')->id();
                if (!$loginUserId) {
                    return $this->sendResponse(false, 'Unauthorized access', [], 401);
                }

                $user = User::find($loginUserId);
                if (!$user) {
                    return $this->sendResponse(false, 'User not found', [], 404);
                }

                $latitude = $user->user_latitude ?? 23.8103;
                $longitude = $user->user_longitude ?? 90.3843;

                // Validate coordinates
                $validator = Validator::make([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ], [
                    'latitude' => 'required|numeric|between:-90,90',
                    'longitude' => 'required|numeric|between:-180,180',
                ]);

                if ($validator->fails()) {
                    $error = $validator->errors()->all(':message');

                    return $this->sendResponse(FALSE, $error[0]);
                }

                $countryName = $this->getDistanceUsingOpenCageData($longitude, $latitude);
                if (!$countryName) {
                    return $this->sendResponse(true, 'Country not found', [], 500);
                }

                $statesCities = $this->getStatesCitiesDataUsingCountriesNow($countryName);
                if (!$statesCities) {
                    return $this->sendResponse(true, 'Cities not found', [], 500);
                }

                return $this->sendResponse(true, 'Location data found',
                    [
                        'Country' => $countryName,
                        'Cities' => $statesCities
                    ]);

            } catch(Exception $e) {
                // Log the error
                Log::error('Error in get location: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return $this->sendResponse(false, 'Something went wrong!!!', [], 500);
            }
        }
        private function getDistanceUsingOpenCageData($longitude, $latitude)
        {
            // Get config values from env
            $baseUrl = config('services.opencagedata.base_url');
            $apiKey = config('services.opencagedata.api_key');

            // Validate config values
            if (empty($baseUrl) || empty($apiKey)) {
                return false;
            }

            // Build API endpoint
            $url = $baseUrl . '?' . http_build_query([
                    'q' => "$latitude,$longitude",
                    'key' => $apiKey,
                ]);

            try {
                // Call API using Laravel's HTTP client
                $response = Http::get($url);

                // Check if response is successful
                if (!$response->successful()) {
                    return false;
                }

                // Parse JSON response
                $data = $response->json();

                // Validate response structure
                if (!isset($data['results']) || empty($data['results']) || !isset($data['results'][0]['components'])) {
                    return false;
                }

                // Extract country data
                $components = $data['results'][0]['components'];
                $countryData = $components['country'] ?? null;

                return $countryData;

            } catch (\Exception $e) {
                // Log the error
                Log::error('Error in fetching location data using OpenCageData: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return false;
            }
        }
        private function getStatesCitiesDataUsingCountriesNow($country)
        {
            // Get config values from env
            $baseUrl = config('services.countries_now.base_url');

            // Validate config values
            if (empty($baseUrl)) {
                return false;
            }

            // Build API endpoint
            $url = rtrim($baseUrl, '/') . '/countries/cities';

            try {
                // Call API using Laravel's HTTP client with POST method
                $response = Http::post($url, [
                    'country' => $country
                ]);

                // Check if response is successful
                if (!$response->successful()) {
                    Log::error('CountriesNow API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    return false;
                }

                // Parse JSON response
                $data = $response->json();

                // Validate response structure
                if (!isset($data['error']) || $data['error'] === true || !isset($data['data']) || empty($data['data'])) {
                    Log::error('Invalid or empty response from CountriesNow API', [
                        'response' => $data
                    ]);
                    return false;
                }

                // Extract cities data
                $cities = $data['data'];

                return $cities;

            } catch (\Exception $e) {
                // Log the error
                Log::error('Error in fetching states and cities data using CountriesNow: ', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                return false;
            }
        }
        private function getDistanceUsingGeoapify($longitude, $latitude, $radius) {
            // env থেকে config
            $baseUrl = config('services.geoapify.base_url');
            $apiKey = config('services.geoapify.api_key');

            // Build API endpoint
            $url = $baseUrl . '?' . http_build_query([
                    'categories' => 'tourism.sights,religion',
                    'filter' => "circle:$longitude,$latitude,$radius",
                    'bias' => "proximity:$longitude,$latitude",
                    'apiKey' => $apiKey,
                ]);

            // Call API using Laravel's HTTP client
            $response = Http::get($url);

            if ($response->successful()) {
                return $response->json();
            }
            return false;
        }
    }
