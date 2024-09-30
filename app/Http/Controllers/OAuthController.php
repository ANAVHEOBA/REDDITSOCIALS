<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite; // Keep this if you're using Socialite
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http; // Ensure to use the correct import for HTTP requests
use App\Models\User;

class OAuthController extends Controller
{
    // Redirect to Reddit OAuth page using Socialite
    public function redirectToReddit()
    {
        return Socialite::driver('reddit')->redirect();
    }

    // Handle the callback from Reddit OAuth using Socialite
    public function handleRedditCallback()
    {
        $redditUser = Socialite::driver('reddit')->user();

        // Find or create the user in the database
        $user = User::updateOrCreate([
            'reddit_id' => $redditUser->id,
        ], [
            'name' => $redditUser->name ?? $redditUser->nickname,
            'email' => $redditUser->email,
            'reddit_token' => $redditUser->token,
            'reddit_refresh_token' => $redditUser->refreshToken,
        ]);

        // Log in the user
        Auth::login($user);

        return response()->json(['message' => 'User authenticated successfully', 'user' => $user]);
    }

    // Handle the callback for manual OAuth (if you want to keep this functionality)
    public function handleManualRedditCallback(Request $request)
    {
        $code = $request->input('code');
        $client = new \GuzzleHttp\Client();

        // Exchange the authorization code for an access token
        try {
            $response = $client->post('https://www.reddit.com/api/v1/access_token', [
                'auth' => [env('REDDIT_CLIENT_ID'), env('REDDIT_CLIENT_SECRET')], // Use environment variables
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => env('REDDIT_REDIRECT_URI'), // Use environment variable
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            if (isset($data['error'])) {
                return redirect()->route('your.redirect.route')->with('error', 'Failed to retrieve access token: ' . $data['error']);
            }

            $accessToken = $data['access_token'];

            // Store the access token in the user's record
            $user = Auth::user();
            $user->reddit_access_token = $accessToken; // Ensure this field exists in your User model
            $user->save();

            return redirect()->route('your.redirect.route'); // Replace with the route you want to redirect to after saving
        } catch (\Exception $e) {
            Log::error('Reddit OAuth Error: ' . $e->getMessage());
            return redirect()->route('your.redirect.route')->with('error', 'Failed to retrieve access token: ' . $e->getMessage());
        }
    }

    // Redirect to Slack OAuth page
    public function redirectToSlack()
    {
        $url = "https://slack.com/oauth/v2/authorize?" . http_build_query([
            'client_id' => env('SLACK_CLIENT_ID'),
            'scope' => 'channels:read,chat:write', // Add the scopes you need
            'redirect_uri' => env('SLACK_REDIRECT_URI'),
            'state' => 'random_string', // Implement CSRF protection here
        ]);

        return redirect($url);
    }

    // Handle the callback from Slack
    public function handleSlackCallback(Request $request)
    {
        // Validate the state parameter for CSRF protection
        $state = $request->input('state');
        if ($state !== 'random_string') {
            return redirect()->route('your.redirect.route')->with('error', 'Invalid state parameter.');
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('your.redirect.route')->with('error', 'Authorization code not provided.');
        }

        try {
            // Exchange authorization code for access token
            $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
                'client_id' => env('SLACK_CLIENT_ID'),
                'client_secret' => env('SLACK_CLIENT_SECRET'),
                'code' => $code,
                'redirect_uri' => env('SLACK_REDIRECT_URI'),
            ]);

            $data = $response->json();

            if (!$data['ok']) {
                return redirect()->route('your.redirect.route')->with('error', 'Failed to retrieve access token: ' . $data['error']);
            }

            $accessToken = $data['access_token'];
            $userSlackId = $data['authed_user']['id'];

            // Save the access token in the user model
            $user = Auth::user();
            $user->slack_access_token = $accessToken;
            $user->slack_id = $userSlackId;
            $user->save();

            return redirect()->route('your.redirect.route')->with('success', 'Slack access token saved successfully.');
        } catch (\Exception $e) {
            Log::error('Slack OAuth Error: ' . $e->getMessage());
            return redirect()->route('your.redirect.route')->with('error', 'Failed to retrieve access token: ' . $e->getMessage());
        }
    }
}
