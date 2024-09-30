<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    public function publishPost(Request $request)
{
    // Validate the incoming request data
    $request->validate([
        'title' => 'required|string|max:300',
        'content' => 'nullable|string',
        'media' => 'nullable|file|mimes:jpeg,png,gif,mp4,mov,avi|max:102400', // 100MB max
        'subreddit' => 'required|string',
    ]);

    $client = new Client();
    $user = Auth::user();

    try {
        $accessToken = $user->reddit_access_token;

        // Prepare the base post data
        $postData = [
            'title' => $request->input('title'),
            'sr' => $request->input('subreddit'),
            'kind' => 'self', // Default to text post
        ];

        // Handle text content
        if ($request->has('content')) {
            $postData['text'] = $request->input('content');
        }

        // Handle media upload
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaType = explode('/', $file->getMimeType())[0];
            
            if ($mediaType === 'image') {
                $mediaUrl = $this->uploadImageToImgur($file);
            } elseif ($mediaType === 'video') {
                $mediaUrl = $this->uploadVideoToReddit($file, $accessToken);
            }

            if (isset($mediaUrl)) {
                $postData['kind'] = 'link';
                $postData['url'] = $mediaUrl;
            }
        }

        // Ensure that at least content or media is provided
        if (!isset($postData['text']) && !isset($postData['url'])) {
            return response()->json([
                'success' => false,
                'message' => 'Post must contain either text content or media.',
                'result' => null,
            ], 400);
        }

        // Make the request to publish the post on Reddit
        $response = $client->post('https://oauth.reddit.com/api/submit', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'YourApp/0.1 by YourUsername',
            ],
            'form_params' => $postData,
        ]);

        $data = json_decode($response->getBody(), true);

        return response()->json([
            'success' => true,
            'message' => 'Post published successfully',
            'result' => $data,
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

private function uploadImageToImgur($image)
{
    $client = new Client();

    try {
        $response = $client->post('https://api.imgur.com/3/image', [
            'headers' => [
                'Authorization' => 'Client-ID ' . env('IMGUR_CLIENT_ID'),
            ],
            'multipart' => [
                [
                    'name' => 'image',
                    'contents' => fopen($image->getPathname(), 'r'),
                ],
            ],
        ]);

        $responseData = json_decode($response->getBody(), true);

        if ($responseData['success']) {
            return $responseData['data']['link'];
        } else {
            Log::error('Imgur upload failed: ' . json_encode($responseData));
            throw new \Exception('Failed to upload image to Imgur');
        }
    } catch (RequestException $e) {
        Log::error('Imgur upload error: ' . $e->getMessage());
        throw new \Exception('Failed to upload image to Imgur: ' . $e->getMessage());
    }
}

// Helper function to upload video directly to Reddit
private function uploadVideoToReddit($video, $accessToken)
{
    $client = new Client();

    try {
        // 1. Get upload lease
        $leaseResponse = $client->post('https://oauth.reddit.com/api/v1/media/asset.json', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => 'YourApp/0.1 by YourUsername',
            ],
            'form_params' => [
                'filepath' => basename($video->getClientOriginalName()),
                'mimetype' => $video->getMimeType(),
            ],
        ]);

        $leaseData = json_decode($leaseResponse->getBody(), true);

        // 2. Upload the video to the provided S3 URL
        $uploadResponse = $client->put($leaseData['args']['action'], [
            'headers' => $leaseData['args']['fields'],
            'body' => fopen($video->getPathname(), 'r'),
        ]);

        // Check if the upload was successful
        if ($uploadResponse->getStatusCode() !== 201) {
            throw new \Exception('Failed to upload video to Reddit S3');
        }

        // 3. Return the Reddit media URL
        return $leaseData['asset']['asset_url'];
    } catch (RequestException $e) {
        Log::error('Reddit video upload error: ' . $e->getMessage());
        throw new \Exception('Failed to upload video to Reddit: ' . $e->getMessage());
    }
}


    // Method to post a message to Slack
    public function postMessageToSlack(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'channel' => 'required|string', // Channel ID or name
            'message' => 'required|string', // The message to post
        ]);

        $client = new Client();
        $user = Auth::user();

        try {
            // Use the stored Slack access token
            $accessToken = $user->slack_access_token;

            // Post the message to Slack
            $response = $client->post('https://slack.com/api/chat.postMessage', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'channel' => $request->input('channel'), // Channel ID or name
                    'text' => $request->input('message'), // The message text
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (!$data['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to post message to Slack: ' . $data['error'],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Message posted successfully to Slack',
                'result' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to post message to Slack: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to post message: ' . $e->getMessage(),
            ], 500);
        }
    }
}
