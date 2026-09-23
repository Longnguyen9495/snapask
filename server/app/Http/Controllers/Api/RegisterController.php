<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * Mở tài khoản mới và cấp luôn token cho máy vừa đăng ký.
     *
     * Cấp token ngay tại đây để người dùng không phải gõ lại mật khẩu ở bước
     * đăng nhập ngay sau khi vừa tạo tài khoản.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'password']),
            'plan' => config('snapask.plans.default.name'),
            'monthly_ask_limit' => config('snapask.plans.default.monthly_ask_limit'),
        ]);

        $device = (string) $request->string('device_name', 'SnapAsk');

        return response()->json([
            'token' => $user->createToken($device)->plainTextToken,
            'user' => $user->only(['id', 'name', 'email']),
        ], 201);
    }
}
