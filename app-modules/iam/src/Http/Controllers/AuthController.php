<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use App\Models\Company\CompanyMember;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Lahatre\Iam\Http\Requests\LoginRequest;

class AuthController
{
    public function login(LoginRequest $request, string $type): JsonResponse
    {
        $authenticatable = match ($type) {
            'user'           => User::where('email', $request->email)->first(),
            'company-member' => CompanyMember::where('email', $request->email)->first(),
            default          => null,
        };

        if (!$authenticatable || !Hash::check($request->password, $authenticatable->password)) {
            return response()->json([
                'message' => 'Invalid login details',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $metadata = match ($type) {
            'user'           => ['type' => 'user', 'company_id' => null],
            'company-member' => ['type' => 'agent', 'company_id' => $authenticatable->company_id],
            default          => null,
        };

        $token = $authenticatable->createToken('auth_token', ['*'], now()->addDay());
        $token->accessToken->update(['metadata' => $metadata]);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'user'         => $authenticatable,
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json(authContext()->user());
    }

    public function logout(): JsonResponse
    {
        $user = authContext()->user();
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }
}
