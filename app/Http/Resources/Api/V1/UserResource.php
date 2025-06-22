<?php

    namespace App\Http\Resources\Api\V1;

    use Illuminate\Http\Request;
    use Illuminate\Http\Resources\Json\JsonResource;

    class UserResource extends JsonResource
    {
        /**
         * Transform the resource into an array.
         *
         * @return array<string, mixed>
         */
        public function toArray(Request $request): array
        {
            $user = [
                "user_id" => $this->user_id,
                "user_phone" => $this->user_phone,
                "user_name" => $this->user_name,
                "user_firstname" => $this->user_firstname,
                "user_lastname" => $this->user_lastname,
                "user_email" => $this->user_email,
                "user_birthdate" => $this->user_birthdate,
                "user_gender" => $this->user_gender,
                "user_gender_text" => $this->userGender->gender_name,
                "user_country" => $this->user_country,
                "user_country_text" => $this->userCountry->country_name ?? null,
                "user_language" => $this->user_language,
                "user_biography" => $this->user_biography,
                "user_latitude" => $this->user_latitude,
                "user_longitude" => $this->user_longitude,
                "user_location_updated" => $this->user_location_updated,
                // "user_language_text" => $this->userLanguage,
                "user_picture" => !empty($this->user_picture) ? $this->user_picture : null,
                "user_cover" => !empty($this->user_cover) ? $this->user_cover : null,
                "user_interests" => !empty($this->user_interests) ? $this->user_interests : null,
                "is_following" => (bool) $this->is_following == 1,
                "is_requested" => (bool) $this->is_requested,
                "is_blocked" => (bool) $this->is_blocked,
                "followings" => $this->followings_count ?? 0,
                "followers" => $this->followers_count ?? 0,
                "device_type" => $this->device_type ?? 0,
                "device_token" => $this->device_token,
                "user_privacy_followers" => $this->user_privacy_followers,
                "token" => $this->token ?? null
            ];

            return $user;
        }
    }
