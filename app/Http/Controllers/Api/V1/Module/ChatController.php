<?php

    namespace App\Http\Controllers\Api\V1\Module;

    use App\Events\SendMessage;
    use App\Http\Controllers\Api\BaseController;
    use App\Models\Conversation;
    use App\Models\ConversationMessage;
    use App\Models\ConversationUser;
    use App\Traits\UploadFileToStorage;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Validator;

    class ChatController extends BaseController
    {
        use UploadFileToStorage;

        public function index(Request $request)
        {
            try {
                $loginUserId = auth('sanctum')->id();

                $conversations = DB::table('conversations_users as c1')
                    ->join('conversations_users as c2', function ($join) {
                        $join->on('c1.conversation_id', '=', 'c2.conversation_id')
                            ->whereColumn('c1.user_id', '!=', 'c2.user_id');
                    })
                    ->join('users as u', 'c2.user_id', '=', 'u.user_id')
                    ->where('c1.user_id', $loginUserId) // Replace with actual user ID
                    ->groupBy(
                        'c1.conversation_id',
                        'u.user_id',
                        'u.user_name',
                        'u.user_firstname',
                        'u.user_lastname',
                        'u.user_picture',
                        'u.user_phone',
                        'u.user_email'
                    )
                    ->select(
                        'c1.conversation_id',
                        'u.user_id',
                        'u.user_name',
                        'u.user_firstname',
                        'u.user_lastname',
                        'u.user_picture',
                        'u.user_phone',
                        'u.user_email'
                    )
                    ->get();

                if (!empty($conversations)) {
                    return $this->sendResponse(TRUE, 'Messages found', $conversations);
                }

                return $this->sendResponse(FALSE, 'No messages found');
            } catch (\Exception $e) {

                // Log the error
                Log::error('Error in retrieving Conversations: ' , [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                # return $this->sendResponse(FALSE, $e->getMessage());
                return $this->sendResponse(FALSE, 'Something went wrong!!!', []);
            }
        }

        public function messages(Request $request)
        {
            $rules = [
                'conversation_id' => 'required|exists:conversations_messages,conversation_id',
            ];

            $validator = Validator::make($request->post(), $rules);
            if ($validator->fails()) {
                $error = $validator->errors()->all(':message');

                return $this->sendResponse(FALSE, $error[0]);
            }

            $loginUserId = auth('sanctum')->id();
            $conversationId = $request->post('conversation_id');

            $messages = ConversationMessage::where('conversation_id', $conversationId)
                ->where('user_id', $loginUserId)
                ->get();

            if (!empty($messages)) {
                ConversationUser::where('conversation_id', $conversationId)
                    ->where('user_id', $loginUserId)->update(['seen' => "1"]);

                return $this->sendResponse(TRUE, 'Messages found', $messages);
            }

            return $this->sendResponse(FALSE, 'No messages found');
        }

        public function store(Request $request)
        {
            try {
                $rules = [
                    'user_id' => 'required',
                    'message_type' => 'required', // 1 = message, 2 = image, 3 = voice_note
                    'message' => 'required_if:message_type,1|string|max:1000',
                    'image' => 'required_if:message_type,2|mimetypes:image/jpeg,image/png|max:2048',
                    'voice_note' => 'required_if:message_type,3|mimetypes:audio/mpeg,audio/mp3|max:2048',
                ];

                $validator = Validator::make($request->all(), $rules);
                if ($validator->fails()) {
                    $error = $validator->errors()->all(':message');

                    return $this->sendResponse(FALSE, $error[0]);
                }

                $loginUserId = auth('sanctum')->id();
                $userId = $request->post('user_id');
                $messageType = $request->post('message_type');
                $message = $request->post('message');
                $image = NULL;
                $voiceNote = NULL;

                if ($loginUserId == $userId) {
                    return $this->sendResponse(FALSE, 'Invalid User');
                }

                if ($messageType == 2) {
                    $media = $request->file('image');
                    $fileName = time() . '.' . $media->getClientOriginalName();

                    $image = $this->verifyAndUpload($request->file('image'), $fileName, 'posts/' . date('Y') . '/' . date('m') . '/comments/');
                }
                else if ($messageType == 3) {
                    $media = $request->file('voice_note');
                    $fileName = time() . '.' . $media->getClientOriginalName();

                    $voiceNote = $this->verifyAndUpload($request->file('voice_note'), $fileName, 'posts/' . date('Y') . '/' . date('m') . '/comments/');
                }

                // Find an existing conversations
                $conversation = ConversationUser::selectRaw("conversation_id")->whereIn('user_id', [$loginUserId, $userId])
                    ->groupBy('conversation_id')
                    ->havingRaw("COUNT(DISTINCT user_id) = 2")->first();

                if (empty($conversation)) {
                    $conversationMessagesCnt = ConversationMessage::count() + 1;
                    $conversation = Conversation::create(['last_message_id' => $conversationMessagesCnt]);

                    ConversationUser::create([
                        'conversation_id' => $conversation->conversation_id,
                        'user_id' => $loginUserId,
                        'seen' => "1"
                    ]);

                    ConversationUser::create([
                        'conversation_id' => $conversation->conversation_id,
                        'user_id' => $userId
                    ]);
                }
                else {
                    ConversationUser::where([
                        'conversation_id' => $conversation->conversation_id,
                        'user_id' => $loginUserId
                    ])->update(['seen' => "1"]);

                    ConversationUser::where([
                        'conversation_id' => $conversation->conversation_id,
                        'user_id' => $userId
                    ])->update(['seen' => "0"]);
                }

                ConversationMessage::create([
                    'conversation_id' => $conversation->conversation_id,
                    'user_id' => $loginUserId,
                    'message' => $message,
                    'image' => $image,
                    'voice_note' => $voiceNote,
                    'time' => now()->format('Y-m-d H:i:s')
                ]);

                ConversationMessage::create([
                    'conversation_id' => $conversation->conversation_id,
                    'user_id' => $userId,
                    'message' => $message,
                    'image' => $image,
                    'voice_note' => $voiceNote,
                    'time' => now()->format('Y-m-d H:i:s')
                ]);

                broadcast(new SendMessage($conversation))->toOthers();

                return $this->sendResponse(TRUE, 'Message sent successfully');
            } catch (\Exception $e) {

                // Log the error
                Log::error('Error in retrieving store Conversation: ' , [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);

                # return $this->sendResponse(FALSE, $e->getMessage());
                return $this->sendResponse(FALSE, 'Something went wrong!!!', []);
            }
        }
    }
