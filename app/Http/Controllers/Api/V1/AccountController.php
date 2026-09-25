<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Services\VideoCallService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in user's own settings, plus feedback and video calls. */
class AccountController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly AccountService $account) {}

    public function updateFirebaseToken(Request $request): JsonResponse
    {
        $token = $request->validate(['firebase_token' => ['present', 'nullable', 'string', 'max:4096']])['firebase_token'];

        $this->account->updateFirebaseToken($request->user(), $token);

        return $this->response(true, 'Device registered.');
    }

    /** A doctor's signature (base64 PNG) for prescriptions. */
    public function saveSignature(Request $request): JsonResponse
    {
        $image = $request->validate(['image' => ['required', 'string']])['image'];

        $this->account->saveSignature($request->user(), $image);

        return $this->response(true, 'Signature saved.', 200, ['digitalsignature' => $request->user()->digitalsignature]);
    }

    public function feedback(Request $request): JsonResponse
    {
        $text = $request->validate(['feedback' => ['required', 'string', 'max:5000']])['feedback'];

        $this->account->submitFeedback($request->user(), $text);

        return $this->response(true, 'Thank you for your feedback.', 201);
    }

    /** Ring a colleague (by username) into a video room. */
    public function videoCall(Request $request, VideoCallService $calls): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'video_room_url' => ['required', 'url', 'max:500'],
        ]);

        $calls->callUser($data['username'], $request->user(), $data['video_room_url']);

        return $this->response(true, 'Calling.');
    }
}
