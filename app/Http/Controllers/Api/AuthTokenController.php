<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:64'],
        ]);

        $user = User::whereHas('entreprise', function ($q) use ($request) {
            $q->where('name', $request->company_name);
        })
            ->where('email', $request->email)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        // Révoquer les anciens tokens de cet appareil
        $user->tokens()
            ->where('name', 'tauri-'.$request->device_id)
            ->delete();

        $token = $user->createToken(
            'tauri-'.$request->device_id,
            ['sync:push', 'sync:pull']
        );

        // Sauvegarder le token localement pour le SyncWorker PHP (chiffré avec APP_KEY)
        file_put_contents(storage_path('app/sync_token'), encrypt($token->plainTextToken));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'entreprise' => [
                'id' => $user->entreprise->id,
                'name' => $user->entreprise->name,
                'plan' => $user->entreprise->plan,
            ],
        ]);
    }

    public function issueTauri(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();

        $user->tokens()
            ->where('name', 'tauri-'.$request->device_id)
            ->delete();

        $token = $user->createToken(
            'tauri-'.$request->device_id,
            ['sync:push', 'sync:pull']
        );

        // Sauvegarder le token localement pour le SyncWorker PHP (chiffré avec APP_KEY)
        file_put_contents(storage_path('app/sync_token'), encrypt($token->plainTextToken));

        return response()->json([
            'token' => $token->plainTextToken,
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['revoked' => true]);
    }
}
