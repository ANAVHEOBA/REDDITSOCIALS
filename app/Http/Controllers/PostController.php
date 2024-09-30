<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    public function redirectToReddit()
    {
        $url = "https://www.reddit.com/api/v1/authorize?" . http_build_query([
            'client_id' => env('REDDIT_CLIENT_ID'), // Use environment variable
            'response_type' => 'code',
            'state' => 'random_string', // Use a secure random string
            'redirect_uri' => env('REDDIT_REDIRECT_URI'), // Use environment variable
            'duration' => 'permanent',
            'scope' => 'read,submit',
        ]);

        return redirect($url);
    }

    public function handleRedditCallback(Request $request)
    {
        $code = $request->input('code');

        if (!$code) {
            return redirect()->route('your.redirect.route')->with('error', 'Authorization code not provided.');
        }

        $client = new Client();

        try {
            $response = $client->post('https://www.reddit.com/api/v1/access_token', [
                'auth' => [env('REDDIT_CLIENT_ID'), env('REDDIT_CLIENT_SECRET')], // Use environment variable
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => env('REDDIT_REDIRECT_URI'), // Use environment variable
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $accessToken = $data['access_token'];

            $user = Auth::user();
            $user->reddit_access_token = $accessToken;
            $user->save();

            return redirect()->route('your.redirect.route')->with('success', 'Access token saved successfully.');
        } catch (\Exception $e) {
            Log::error('Reddit OAuth Error: ' . $e->getMessage());
            return redirect()->route('your.redirect.route')->with('error', 'Failed to retrieve access token: ' . $e->getMessage());
        }
    }

    // New method to handle publishing posts
    public function publishPost(Request $request)
    {
        // Validate the incoming request data
        $request->validate([
            'title' => 'required|string|max:300', // Example validation
            'content' => 'required|string', // Example validation
            'subreddit' => 'required|string', // The subreddit where the post will be published
        ]);

        $client = new Client();
        $user = Auth::user();

        try {
            // Use the stored Reddit access token
            $accessToken = $user->reddit_access_token;

            // Prepare the post data
            $postData = [
                'title' => $request->input('title'),
                'content' => $request->input('content'),
                'subreddit' => $request->input('subreddit'),
            ];

            // Make the request to publish the post on Reddit
            $response = $client->post('https://oauth.reddit.com/api/submit', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'User-Agent' => 'YourApp/0.1 by YourUsername', // Change this to a user agent
                ],
                'form_params' => [
                    'title' => $postData['title'],
                    'text' => $postData['content'],
                    'sr' => $postData['subreddit'],
                    'kind' => 'self', // 'self' for text post
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            return response()->json([
                'success' => true,
                'message' => 'Post published successfully',
                'result' => $data, // Return the published post data if needed
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish post: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish post: ' . $e->getMessage(),
                'result' => null,
            ], 500);
        }
    }
}
