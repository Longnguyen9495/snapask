<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    /**
     * Cấp một token cho máy vừa đăng nhập.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        // Chỉ email và mật khẩu mới là thông tin đăng nhập. Để lọt `device_name`
        // vào đây thì nó bị dựng thành một điều kiện `where` trên bảng users và
        // không tài khoản nào khớp được.
        $credentials = Arr::only($validated, ['email', 'password']);

        if (! Auth::validate($credentials)) {
            // Một thông báo chung cho cả email sai lẫn mật khẩu sai, để không
            // biến biểu mẫu đăng nhập thành công cụ dò xem email nào có thật.
            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không đúng.',
            ]);
        }

        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        // Mỗi máy giữ đúng một token: đăng nhập lại trên cùng máy thì thu hồi
        // token cũ, tránh để lại token mồ côi không ai gỡ được.
        $user->tokens()->where('name', $validated['device_name'])->delete();

        return response()->json([
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }

    /** Thu hồi đúng token đang dùng để gọi. */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['revoked' => true]);
    }
}
