<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationVerificationCode;
use App\Mail\VerifyEmail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Support\SiteMailBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;
use URL;
use Throwable;

// Для отправки письма с подтверждением

class AuthController extends Controller
{

    // Логин пользователя
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();

            return response()->json([
                'message' => 'User is blocked',
            ], 403);
        }

        // Проверяем, подтвержден ли email
        if ($user->email_verified_at === null) {
            Auth::logout(); // Выход из системы
            return response()->json([
                'message' => 'Email not verified',
            ], 403); // 403 Forbidden
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $token,
        ]);
    }

    // Выход пользователя
    public function logout(Request $request): JsonResponse
    {
        // Проверяем, аутентифицирован ли пользователь
        if ($request->user()) {
            $request->user()->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully']);
        }

        // Если пользователь не аутентифицирован, возвращаем ошибку
        return response()->json(['message' => 'User not authenticated'], 401);
    }

    public function user(Request $request): JsonResponse
    {
        if ($request->user()?->is_blocked) {
            return response()->json([
                'message' => 'User is blocked',
            ], 403);
        }

        return response()->json($request->user());
    }

    /**
     * Регистрация нового пользователя
     */

    // Импортируйте ваш Mailable-класс

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user?->email_verified_at) {
            return response()->json(['errors' => ['email' => ['Этот email уже зарегистрирован.']]], 422);
        }

        if ($user && ! Hash::check($request->password, $user->password)) {
            return response()->json(['errors' => ['email' => ['Для незавершённой регистрации укажите исходный пароль или восстановите пароль.']]], 422);
        }

        if (! $user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
        }

        try {
            $this->sendRegistrationVerificationCode($user);
        } catch (Throwable $exception) {
            Log::error('Registration verification code sending failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Не удалось отправить код. Попробуйте ещё раз через минуту.'], 500);
        }

        return response()->json([
            'message' => 'Код подтверждения отправлен на вашу почту.',
            'verification_required' => true,
            'email' => $user->email,
        ], 201);
    }

    public function verifyRegistrationCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();
        $verification = $user ? EmailVerificationCode::where('user_id', $user->id)->first() : null;

        if (! $user || ! $verification || $verification->expires_at->isPast()) {
            return response()->json(['message' => 'Код недействителен или срок его действия истёк. Запросите новый код.'], 422);
        }

        if ($verification->attempts >= 5) {
            return response()->json(['message' => 'Превышено число попыток. Запросите новый код.'], 429);
        }

        if (! hash_equals($verification->code_hash, hash('sha256', $request->code))) {
            $verification->increment('attempts');

            return response()->json(['message' => 'Неверный код. Проверьте цифры и попробуйте ещё раз.'], 422);
        }

        $user->markEmailAsVerified();
        $verification->delete();

        return response()->json([
            'message' => 'Email подтверждён.',
            'user' => $this->userPayload($user),
            'token' => $user->createToken('auth_token')->plainTextToken,
        ]);
    }

    public function resendRegistrationCode(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|string|email']);

        $user = User::where('email', $request->email)->first();
        if ($user && ! $user->email_verified_at) {
            try {
                $this->sendRegistrationVerificationCode($user);
            } catch (Throwable $exception) {
                Log::error('Registration verification code resend failed', ['user_id' => $user->id, 'message' => $exception->getMessage()]);

                return response()->json(['message' => 'Не удалось отправить код. Попробуйте ещё раз через минуту.'], 500);
            }
        }

        return response()->json(['message' => 'Если регистрация не завершена, новый код отправлен на почту.']);
    }

    private function sendRegistrationVerificationCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        EmailVerificationCode::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(15),
                'attempts' => 0,
                'last_sent_at' => now(),
            ]
        );

        Mail::to($user->email)->send(new RegistrationVerificationCode($code));
    }


    /**
     * Повторная отправка письма с подтверждением email
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        return $this->resendRegistrationCode($request);
    }



// ВОССТАНОВЛЕНИЕ ПАРОЛЯ

    /**
     * Отправка ссылки для восстановления пароля
     */
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Введите корректный email',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Находим пользователя по email
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Пользователь с таким email не найден'], 404);
        }

        try {
            // Генерируем токен для сброса пароля
            $token = Password::createToken($user);

            $resetBaseUrl = rtrim((string) config('app.frontend_url'), '/');

            // Формируем ссылку для сброса пароля с параметрами token и email
            $resetUrl = $resetBaseUrl . '/auth/reset-password?token=' . $token . '&email=' . urlencode($user->email);

            // Отправляем письмо с кастомной ссылкой
            Mail::send('emails.password_reset', [
                'resetUrl' => $resetUrl,
                'brand' => SiteMailBranding::data(),
            ], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Сброс пароля');
            });
        } catch (Throwable $exception) {
            Log::error('Password reset email sending failed', [
                'email' => $request->email,
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'message' => 'Не удалось отправить письмо для восстановления пароля. Проверьте настройки почты на сервере.',
            ], 500);
        }

        return response()->json(['message' => 'Ссылка для восстановления отправлена']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        // Валидация данных
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        // Сброс пароля
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // Возвращаем JSON с сообщением об успехе
            return response()->json([
                'message' => 'Пароль успешно изменен',
            ], 200);
        } else {
            // Возвращаем JSON с сообщением об ошибке
            return response()->json([
                'message' => 'Ошибка сброса пароля',
            ], 400);
        }
    }

// ВОССТАНОВЛЕНИЕ ПАРОЛЯ


    public function generateCaptcha(): JsonResponse
    {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operation = ['+', '-', '*'][rand(0, 2)]; // Случайная операция

        $question = "$num1 $operation $num2";
        $answer = eval("return $num1 $operation $num2;");

        return response()->json([
            'question' => $question,
            'answer' => $answer,
        ]);
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Пользователь не аутентифицирован.'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email уже подтвержден.'], 400);
        }

        // Генерация подписанной ссылки
        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify', // Имя маршрута с префиксом /api
            now()->addMinutes(60), // Срок действия ссылки
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        // Отправка письма
        Mail::to($user->email)->send(new VerifyEmail($verificationUrl));

        return response()->json(['message' => 'Ссылка для подтверждения отправлена.']);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::find($request->input('id'));

        if (!$user) {
            return response()->json(['message' => 'Пользователь не найден.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email уже подтвержден.'], 400);
        }

        if (!hash_equals((string)$request->input('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Неверная ссылка для подтверждения.'], 400);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email успешно подтвержден.']);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('adminRoles:id,name,slug,is_active');

        $activeAdminRoles = $user->adminRoles
            ->where('is_active', true)
            ->values();

        return [
            ...$user->toArray(),
            'admin_roles' => $activeAdminRoles,
            'has_admin_access' => $user->hasAnyAdminAccess(),
            'has_custom_admin_role' => $user->isAdmin() || $activeAdminRoles->where('slug', '!=', 'user')->isNotEmpty(),
        ];
    }

}
