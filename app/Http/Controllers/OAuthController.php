<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite; // Keep this if you're using Socialite
use GuzzleHttp\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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
        $client = new Client();

        // Exchange the authorization code for an access token
        $response = $client->post('https://www.reddit.com/api/v1/access_token', [
            'auth' => ['YOUR_CLIENT_ID', 'YOUR_CLIENT_SECRET'], // Replace with your client ID and secret
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => 'YOUR_REDIRECT_URI', // Replace with your redirect URI
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        $accessToken = $data['access_token'];

        // Store the access token in the user's record
        $user = Auth::user();
        $user->reddit_access_token = $accessToken; // Make sure this field exists in your User model
        $user->save();

        return redirect()->route('your.redirect.route'); // Replace with the route you want to redirect to after saving
    }
}
