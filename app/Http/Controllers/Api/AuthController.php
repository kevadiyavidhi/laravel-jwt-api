<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Logging\ApiLogger;
use App\Models\User;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    protected ApiLogger $logger;

    public function __construct()
    {
        $this->logger = new ApiLogger;

        $this->middleware('auth:api', [
            'except' => ['login', 'register'],
        ]);
    }

    public function register(RegisterRequest $request)
    {
        try {

            $this->logger->logRequest(
                'Register',
                $request->except([
                    'password',
                    'password_confirmation',
                ])
            );

            $validated = $request->validated();

            $user = User::create($validated);

            $response = [
                'success' => true,
                'message' => 'User registered successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'errors' => [],
            ];

            $this->logger
                ->setContext([
                    'user_id' => $user->id,
                ])
                ->logResponse('Register', $response);

            return response()->json($response);

        } catch (Throwable $e) {

            $this->logger->logException(
                'Register',
                $e,
                [
                    'request' => $request->except([
                        'password',
                        'password_confirmation',
                    ]),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Validation Failed, Please enter valid data',
                'errors' => $e->getMessage(),
            ], 422);
        }
    }

    public function login(LoginRequest $request)
    {
        try {

            $this->logger->logRequest(
                'Login',
                $request->except('password')
            );

            $credentials = $request->only('email', 'password');

            if (! $token = Auth::guard('api')->attempt($credentials)) {

                $this->logger->logWarning(
                    'Login',
                    $request->path(),
                    401,
                    'Invalid email or password'
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password.',
                    'data' => [],
                    'errors' => [],
                ], 401);
            }

            $user = Auth::guard('api')->user();

            $this->logger->setContext([
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            $response = [
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 50,
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('Login', $response);

            return response()->json($response);

        } catch (Throwable $e) {

            $this->logger->logException(
                'Login',
                $e,
                [
                    'email' => $request->email,
                    'request' => $request->except('password'),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Login Failed',
                'data' => [],
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    public function me()
    {
        try {

            $user = Auth::guard('api')->user();

            $this->logger
                ->setContext([
                    'user_id' => $user->id,
                ])
                ->logResponse('Profile', $user->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Profile fetched successfully.',
                'data' => $user,
                'errors' => [],
            ]);

        } catch (Throwable $e) {

            $this->logger->logException('Profile', $e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile.',
                'data' => [],
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    public function logout()
    {
        try {

            $user = Auth::guard('api')->user();

            $this->logger->setContext([
                'user_id' => $user?->id,
            ]);

            Auth::guard('api')->logout();

            $response = [
                'success' => true,
                'message' => 'Successfully logged out.',
                'data' => [],
                'errors' => [],
            ];

            $this->logger->logResponse('Logout', $response);

            return response()->json($response);

        } catch (Throwable $e) {

            $this->logger->logException('Logout', $e);

            return response()->json([
                'success' => false,
                'message' => 'Logout failed.',
                'data' => [],
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    public function refresh()
    {
        try {

            $token = Auth::guard('api')->refresh();

            $response = [
                'success' => true,
                'message' => 'Token refreshed successfully.',
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 50,
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('Refresh Token', $response);

            return response()->json($response);

        } catch (Throwable $e) {

            $this->logger->logException('Refresh Token', $e);

            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed.',
                'data' => [],
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    protected function respondWithToken($token)
    {
        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 50,
        ];
    }
}
