<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ConvertPlaylistRequestDTO;
use App\Http\Controllers\Controller;
use App\Services\PlaylistConverterService;
use App\Services\YouTubeOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlaylistConverterController extends Controller
{
    public function __construct(
        private PlaylistConverterService $converterService,
        private YouTubeOAuthService $oauthService
    ) {
    }

    public function convert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'playlist_id' => 'required|string',
            'oauth_code' => 'nullable|string',
            'state' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $requestDto = ConvertPlaylistRequestDTO::fromRequest($request->all());
            
            // Use session ID as default token identifier (handled automatically)
            $tokenIdentifier = session()->getId();
            
            // Handle OAuth code exchange if provided (no redirect needed)
            $oauthCode = $request->input('oauth_code');
            if ($oauthCode) {
                // Validate state if provided
                $state = $request->input('state');
                if ($state) {
                    $storedState = session('youtube_oauth_state');
                    if ($state !== $storedState) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid state parameter',
                            'error' => 'State mismatch',
                        ], 400);
                    }
                }
                
                // Exchange code for token (use same redirect URI if custom was provided)
                $customRedirectUri = $request->input('redirect_uri');
                $tokenData = $this->oauthService->exchangeCodeForToken($oauthCode, $customRedirectUri);
                $tokenData['expires_at'] = now()->timestamp + ($tokenData['expires_in'] ?? 3600);
                $this->oauthService->storeToken($tokenIdentifier, $tokenData);
                
                // Clear state from session
                session()->forget('youtube_oauth_state');
            }
            
            // Check if we have a valid token
            $accessToken = $this->oauthService->getValidAccessToken($tokenIdentifier);
            
            if (! $accessToken) {
                // Generate authorization URL automatically (client will handle redirect)
                $state = bin2hex(random_bytes(16));
                session(['youtube_oauth_state' => $state]);
                
                // Allow client to provide custom redirect URI
                $customRedirectUri = $request->input('redirect_uri');
                $authUrl = $this->oauthService->getAuthorizationUrl($state, $customRedirectUri);
                
                return response()->json([
                    'success' => false,
                    'message' => 'YouTube authorization required',
                    'requires_authorization' => true,
                    'authorization_url' => $authUrl,
                    'state' => $state,
                    'instructions' => 'Open the authorization_url in a browser, authorize, extract the code from the redirect URL query parameter (?code=...), and call this endpoint again with oauth_code and state parameters',
                ], 401);
            }
            
            // Proceed with conversion using the stored token
            $result = $this->converterService->convert($requestDto->playlistId, $tokenIdentifier);

            return response()->json([
                'success' => true,
                'data' => $result->toArray(),
            ], 200);
        } catch (\RuntimeException $e) {
            $statusCode = 500;
            $message = 'Failed to convert playlist';
            
            // Check if it's an OAuth-related error
            if (str_contains($e->getMessage(), 'token') || str_contains($e->getMessage(), 'OAuth') || str_contains($e->getMessage(), 'authenticate')) {
                $statusCode = 401;
                $message = 'YouTube authentication required';
                
                // Generate authorization URL automatically
                $state = bin2hex(random_bytes(16));
                session(['youtube_oauth_state' => $state]);
                
                // Allow client to provide custom redirect URI
                $customRedirectUri = $request->input('redirect_uri');
                $authUrl = $this->oauthService->getAuthorizationUrl($state, $customRedirectUri);
                
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'error' => $e->getMessage(),
                    'requires_authorization' => true,
                    'authorization_url' => $authUrl,
                    'state' => $state,
                    'instructions' => 'Open the authorization_url in a browser, authorize, extract the code from the redirect URL query parameter (?code=...), and call this endpoint again with oauth_code and state parameters',
                ], $statusCode);
            }
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => $e->getMessage(),
            ], $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert playlist',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

