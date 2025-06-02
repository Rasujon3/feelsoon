<?php

namespace App\Traits;

trait SendPushNotificationTrait
{
    public function sendNotificationOld($language = 'en', $title, $message, $userId = null)
    {
        $filters[] = [
            "field" => "tag", "key" => "language", "relation" => "=", "value" => $language,
        ];

        if (!empty($userId)) {
            $filters[] = [
                "field" => "tag", "key" => "userId", "relation" => "=", "value" => $userId,
            ];
        }

        $fields = [
            'app_id' => config('constants.notification.onesignal.application-id'),
            'filters' => $filters,
            'data' => [
                'message' => $message,
                // 'type' => $type // need to check
            ],
            'contents' => [
                $language => $title,
            ],
            'headings' => [
                $language => ($language == 'ar') ? config('app.name_ar') : config('app.name'),
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . config('constants.notification.onesignal.api-key'),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($response, true);

        if (isset($response['errors'])) {
            info('Send push notification error: ');
            info(print_r($response, true));

            return ['status' => false, 'error' => $response];
        }

        return ['status' => true];
    }

    public function sendNotification($deviceToken, $title, $message, $data = [])
    {
        $serverKey = config('services.firebase.server-key');

        $data["click_action"] = "FLUTTER_NOTIFICATION_CLICK";
        $data["status"] = "done";

        $postData = [
            "message" => [
                "token" => 'dE7ArGDRTReqYH5lLKo7r_:APA91bHQqk342tpVnTYodLuK3wNvPzJgI2imLxs6hU2-tQN-9TN2k5acYKrpQkdZXMJO3tE_98mQGZzjiDCvCt322JhskzSRWF7ME3lD61NQbvA70j09Bu8',
                "notification" => [
                    "title" => $title,
                    "body" => $message
                ],
                "data" => $data
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $serverKey,
            'Content-Type: application/json',
        ];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/feelsoon-5b501/messages:send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $response = curl_exec($ch);
        curl_close($ch);

        if (isset($response['errors'])) {
            info('Send push notification error: ');
            info(print_r($response, true));

            return ['status' => false, 'error' => $response];
        }

        return ['status' => true];
    }

    public static function sendNotificationStatic($deviceToken, $title, $message, $data = [])
    {
        $serverKey = config('services.firebase.server-key');

        $data["click_action"] = "FLUTTER_NOTIFICATION_CLICK";
        $data["status"] = "done";

        $postData = [
            "message" => [
                "token" => 'dE7ArGDRTReqYH5lLKo7r_:APA91bHQqk342tpVnTYodLuK3wNvPzJgI2imLxs6hU2-tQN-9TN2k5acYKrpQkdZXMJO3tE_98mQGZzjiDCvCt322JhskzSRWF7ME3lD61NQbvA70j09Bu8',
                "notification" => [
                    "title" => $title,
                    "body" => $message
                ],
                "data" => $data
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $serverKey,
            'Content-Type: application/json',
        ];


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/feelsoon-5b501/messages:send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $response = curl_exec($ch);
        curl_close($ch);

        if (isset($response['errors'])) {
            info('Send push notification error: ');
            info(print_r($response, true));

            return ['status' => false, 'error' => $response];
        }

        return ['status' => true];
    }
}
