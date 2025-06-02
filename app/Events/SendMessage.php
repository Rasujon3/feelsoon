<?php

    namespace App\Events;

    use Illuminate\Broadcasting\InteractsWithSockets;
    use Illuminate\Broadcasting\PrivateChannel;
    use Illuminate\Foundation\Events\Dispatchable;
    use Illuminate\Queue\SerializesModels;

    class SendMessage
    {
        use Dispatchable, InteractsWithSockets, SerializesModels;

        /**
         * Create a new event instance.
         */

        public $message;
        public function __construct($message)
        {
            $this->message = $message;
            info('msg ok: ' . $this->message);
        }

        public function broadcastOn()
        {
            // return new PrivateChannel('chat.' . $this->message->receiver_id);
            return new PrivateChannel('chat.1');
        }

        public function broadcastWith()
        {
            return ['message' => $this->message];
        }
    }
