<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponseHelper;
use App\Helpers\SocketHelper;
use App\Http\Requests\SendChatRequest;
use App\Models\ChatHistory;
use App\Models\ChatRoom;
use Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Helpers\RabbitMqHelper;
use App\Jobs\PublishMessageJob;

class ChatController
{
    public function SendChat(SendChatRequest $request)
    {
        $validated = $request->validated();
        DB::beginTransaction();

        $userId = Auth::id();
        $path = null;
        try {
            $chat = $validated['chat'] ?? null;
            if ($request->hasFile('file') && $request->file('file')->isValid()) {
                $extension = $request->file('file')->getClientOriginalExtension();
                $filename = $userId . 'chat_file.' . $extension;
                $path = $request->file('file')->storeAs('chat_files', $filename, 'public');
            }
            $fileUrl = asset('storage/' . $path) ?? null;
            $rabbitMq = new RabbitMqHelper();
            $queue = $rabbitMq->getQueueByIndex(4);

            $chatRoom = ChatRoom::firstOrCreate(
                [
                    'freelancer_id' => $validated['freelancer_id'],
                    'client_id' => $userId,
                ],
                [
                    'id' => Str::uuid(),
                    'freelancer_id' => $validated['freelancer_id'],
                    'client_id' => $userId,
                ]
            );

            if ($chatRoom->wasRecentlyCreated) {
                dispatch(new PublishMessageJob($queue, [
                    'event' => 'Room Chat Created',
                    'data' => $chatRoom->toArray(),
                    'status' => 201,
                ]));
            }

            $socket = new SocketHelper();

            $messageData = [
                'room' => 'tes-room-1',  //nanti diubah ini testing aja
                'user_id' => $userId,
                'chat' => $chat,
            ];
            $chatHistory = ChatHistory::create(
                [
                    'id' => Str::uuid(),
                    'chat_room_id' => $chatRoom->id,
                    'sender_id' => $userId,
                    'chat' => $chat,
                ]
            );

            $socket->TriggerSendChatEvent($messageData);

            DB::commit();

            return ApiResponseHelper::respond(
                $messageData,
                'Chat sent successfully.',
                200
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return ApiResponseHelper::respond(
                null,
                'Failed to send chat: ' . $e->getMessage(),
                500
            );
        }
    }
    public function GetChat(string $freelancerId)
    {
        try {
            $userId = Auth::id();

            $chatRoom = ChatRoom::where('client_id', $userId)
                ->where('freelancer_id', $freelancerId)
                ->first();

            if (!$chatRoom) {
                return ApiResponseHelper::respond(null, 'Chat room not found.', 404);
            }

            $chatHistory = ChatHistory::where('chat_room_id', $chatRoom->id)
                ->orderBy('created_at')
                ->get()
                ->map(function ($chat) {
                    return [
                        'id' => $chat->id,
                        'chats' => $chat->chats,
                        'sender_id' => $chat->sender_id,
                        'created_at' => $chat->created_at,
                        'updated_at' => $chat->updated_at,
                    ];
                });

            $response = [
                'room_id' => $chatRoom->id,
                'chat_history' => $chatHistory,
            ];

            return ApiResponseHelper::respond($response, 'Chat retrieved successfully.', 200);
        } catch (\Exception $e) {
            return ApiResponseHelper::respond(null, 'Get Chat failed, Error : ' . $e->getMessage(), 500);
        }
    }


}
