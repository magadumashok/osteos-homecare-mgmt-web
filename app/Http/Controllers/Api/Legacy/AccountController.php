<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Services\VideoCallService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Legacy endpoints acting on the signed-in user's own account. */
class AccountController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly AccountService $account) {}

    /** GET /api/get-user-details — bare user object. */
    public function profile(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /** POST /api/update-firebase-token {firebase_token} */
    public function updateFirebaseToken(Request $request): JsonResponse
    {
        $this->account->updateFirebaseToken($request->user(), $request->input('firebase_token'));

        return $this->response(true, 'success', 200, '');
    }

    /** POST /api/save-doctor-signature {image: base64 PNG} */
    public function saveSignature(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|string']);

        $fileName = $this->account->saveSignature($request->user(), $request->input('image'));

        return $this->response(true, 'success', 200, $fileName);
    }

    /** POST /api/user_feedback {feedback} */
    public function feedback(Request $request): JsonResponse
    {
        $request->validate(['feedback' => 'required|string|max:5000']);

        $this->account->submitFeedback($request->user(), $request->input('feedback'));

        return $this->response(true, 'User Feedback has been submitted successfully...', 200, '');
    }

    /** POST /api/video-call/notify {fcm_token, title, body, video_room_url} */
    public function notifyVideoCall(Request $request, VideoCallService $calls): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => 'required|string',
            'title' => 'required|string|max:200',
            'body' => 'required|string|max:1000',
            'video_room_url' => 'required|string|max:500',
        ]);

        try {
            $calls->send($data['fcm_token'], $data['title'], $data['body'], $data['video_room_url']);
        } catch (\Throwable $e) {
            Log::error('[VideoCall] FCM send failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => 0, 'error' => 'The call notification could not be sent.'], 500);
        }

        return response()->json(['success' => 1]);
    }
}
