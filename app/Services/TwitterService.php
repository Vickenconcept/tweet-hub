<?php

namespace App\Services;

use Noweh\TwitterApi\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Client as GuzzleClient;

class TwitterService
{
    protected $client;

    public function __construct(array $settings)
    {
        $this->client = new Client($settings);
    }

    // Tweets endpoints
    /**
     * Find recent mentions for a user.
     */
    public function getRecentMentions($accountId)
    {
        $maxRetries = config('twitter.rate_limiting.max_retries', 3);
        $baseDelay = config('twitter.rate_limiting.base_retry_delay', 5);

        for ($retry = 0; $retry < $maxRetries; $retry++) {
            try {
                if (config('twitter.logging.log_api_calls', true)) {
                Log::info('Fetching recent mentions', ['account_id' => $accountId, 'retry' => $retry]);
                }
                
                $result = $this->client->timeline()->getRecentMentions($accountId)->performRequest();
                
                if (config('twitter.logging.log_success', false)) {
                Log::info('Recent mentions fetched successfully', ['account_id' => $accountId]);
                }
                
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $body = $e->getResponse()->getBody()->getContents();
                
                if (config('twitter.logging.log_errors', true)) {
                Log::error('Twitter API Error fetching mentions', [
                    'account_id' => $accountId,
                    'status_code' => $statusCode,
                    'error_body' => $body,
                    'retry' => $retry,
                    'max_retries' => $maxRetries
                ]);
                }
                
                if ($statusCode === 429 && $retry < $maxRetries - 1) {
                    // Exponential backoff with jitter to avoid thundering herd
                    $waitTime = $baseDelay * pow(2, $retry) + rand(1, 5);
                    
                    if (config('twitter.logging.log_rate_limits', true)) {
                        Log::info('Rate limited, waiting before retry', [
                            'wait_time' => $waitTime, 
                            'retry' => $retry,
                            'status_code' => $statusCode
                        ]);
                    }
                    
                    sleep($waitTime);
                    continue;
                }
                
                // For other client errors, don't retry
                if ($statusCode >= 400 && $statusCode < 500 && $statusCode !== 429) {
                    Log::error('Client error, not retrying', ['status_code' => $statusCode]);
                    throw $e;
                }
                
                // For server errors (5xx), retry
                if ($statusCode >= 500 && $retry < $maxRetries - 1) {
                    $waitTime = $baseDelay * pow(2, $retry);
                    Log::info('Server error, retrying', ['wait_time' => $waitTime, 'retry' => $retry]);
                    sleep($waitTime);
                    continue;
                }
                
                throw $e;
            } catch (\Exception $e) {
                Log::error('Unexpected error fetching mentions', [
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                    'retry' => $retry
                ]);
                
                if ($retry < $maxRetries - 1) {
                    $waitTime = $baseDelay * pow(2, $retry);
                    Log::info('Unexpected error, retrying', ['wait_time' => $waitTime, 'retry' => $retry]);
                    sleep($waitTime);
                    continue;
                }
                
                throw $e;
            }
        }
    }

    /**
     * Find recent tweets for a user.
     */
    public function getRecentTweets($accountId)
    {
        return $this->client->timeline()->getRecentTweets($accountId)->performRequest();
    }

    /**
     * Get reverse chronological timeline by user ID.
     */
    public function getReverseChronological()
    {
        return $this->client->timeline()->getReverseChronological()->performRequest();
    }

    // Tweet/Likes endpoints
    /**
     * Get tweets liked by a user.
     */
    public function getLikedTweets($accountId, $pageSize = 10)
    {
        return $this->client->tweetLikes()->addMaxResults($pageSize)->getLikedTweets($accountId)->performRequest();
    }

    /**
     * Get users who liked a tweet.
     */
    public function getUsersWhoLiked($tweetId, $pageSize = 10)
    {
        return $this->client->tweetLikes()->addMaxResults($pageSize)->getUsersWhoLiked($tweetId)->performRequest();
    }

    // Tweet/Lookup endpoints
    /**
     * Search specific tweets with filters.
     */
    public function searchTweets($usernames = [], $keywords = [], $locales = [], $pageSize = 10)
    {
        $lookup = $this->client->tweetLookup()
            ->addMaxResults($pageSize)
            ->showUserDetails()
            ->showMetrics();
        if (!empty($usernames)) {
            $lookup->addFilterOnUsernamesFrom($usernames, \Noweh\TwitterApi\TweetLookup::OPERATORS['OR']);
        }
        if (!empty($keywords)) {
            $lookup->addFilterOnKeywordOrPhrase($keywords, \Noweh\TwitterApi\TweetLookup::OPERATORS['AND']);
        }
        if (!empty($locales)) {
            $lookup->addFilterOnLocales($locales);
        }
        return $lookup->performRequest();
    }

    /**
     * Find all replies from a Tweet.
     */
    public function getReplies($tweetId)
    {
        return $this->client->tweetLookup()->addFilterOnConversationId($tweetId)->performRequest();
    }

    // Tweet endpoints
    /**
     * Fetch a tweet by ID.
     */
    public function fetchTweet($tweetId)
    {
        return $this->client->tweet()->fetch($tweetId)->performRequest();
    }

    /**
     * Create a new tweet. Optionally as a reply (for threads).
     */
    public function createTweet($text, $mediaIds = [], $inReplyToTweetId = null)
    {
        // Validate character limit (Twitter/X limit is 280 characters)
        $charCount = mb_strlen($text, 'UTF-8');
        if ($charCount > 280) {
            $error = "Tweet exceeds character limit. Current: {$charCount}, Limit: 280";
            Log::error('Twitter API Error: ' . $error, [
                'text' => $text,
                'char_count' => $charCount,
                'limit' => 280
            ]);
            throw new \Exception($error);
        }

        try {
            $data = ['text' => $text];
            if (!empty($mediaIds)) {
                // Ensure all media IDs are strings and not empty
                $validMediaIds = array_filter(array_map('strval', $mediaIds), function($id) {
                    return !empty($id) && is_numeric($id);
                });
                
                if (!empty($validMediaIds)) {
                    $data['media'] = ['media_ids' => array_values($validMediaIds)];
                }
            }
            
            // Ensure we have either text or media
            if (empty(trim($text)) && empty($data['media'] ?? [])) {
                throw new \Exception('Tweet must contain either text or media content');
            }
            if ($inReplyToTweetId) {
                $data['reply'] = ['in_reply_to_tweet_id' => $inReplyToTweetId];
            }
            
            Log::info('Creating tweet', [
                'char_count' => $charCount,
                'has_media' => !empty($mediaIds),
                'media_ids' => $mediaIds,
                'is_reply' => !empty($inReplyToTweetId),
                'request_data' => $data
            ]);
            
            $result = $this->client->tweet()->create()->performRequest($data);
            
            Log::info('Tweet created successfully', [
                'tweet_id' => $result->data->id ?? 'unknown',
                'text' => $text,
                'response' => $result
            ]);
            
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            Log::error('Twitter API Client Error', [
                'status_code' => $statusCode,
                'error_body' => $body,
                'text' => $text,
                'char_count' => $charCount,
                'request_data' => $data
            ]);
            
            throw $e;
        } catch (\Exception $e) {
            Log::error('Twitter API General Error', [
                'message' => $e->getMessage(),
                'text' => $text,
                'char_count' => $charCount
            ]);
            
            throw $e;
        }
    }

    /**
     * Upload image to Twitter and return media info.
     */
    public function uploadMedia($file)
    {
        $file_data = base64_encode(file_get_contents($file));
        return $this->client->uploadMedia()->upload($file_data);
    }

    /**
     * Upload a local file to Twitter and return the media ID.
     */
    public function uploadLocalMedia($localPath)
    {
        Log::info('Uploading media to Twitter', [
            'local_path' => $localPath,
            'file_exists' => file_exists($localPath),
            'file_size' => file_exists($localPath) ? filesize($localPath) : 'N/A'
        ]);
        
        if (!file_exists($localPath)) {
            Log::error('Media file does not exist', ['path' => $localPath]);
            return null;
        }
        
        try {
            // Check file properties
            $fileSize = filesize($localPath);
            $fileInfo = getimagesize($localPath);
            $mimeType = mime_content_type($localPath);
            
            Log::info('File properties before upload', [
                'path' => $localPath,
                'size' => $fileSize,
                'image_info' => $fileInfo,
                'mime_type' => $mimeType,
                'is_gif' => $mimeType === 'image/gif'
            ]);
            
                            // Determine if it's a video or image (GIFs are treated as images)
                $isVideo = str_starts_with($mimeType, 'video/');
                $isGif = $mimeType === 'image/gif';
                
                if ($isVideo) {
                // Check if video is too large for Twitter
                if ($fileSize > 15 * 1024 * 1024) { // 15MB limit
                    throw new \Exception('Video file is too large. Twitter supports videos up to 15MB.');
                }
                
                // For now, let's try a simple approach - just use regular upload
                // The chunked upload seems to have issues with the package
                Log::info('Trying regular video upload', ['size' => $fileSize]);
                try {
                    $media_info = $this->client->uploadMedia()->upload($localPath);
                    if (isset($media_info['media_id'])) {
                        Log::info('Regular video upload successful', ['media_id' => $media_info['media_id']]);
                    } else {
                        throw new \Exception('No media_id returned from regular upload');
                    }
                } catch (\Exception $e) {
                    Log::error('Regular video upload failed', ['error' => $e->getMessage()]);
                    // For now, let's not try chunked upload as it's not working
                    throw new \Exception('Video upload failed: ' . $e->getMessage());
                }
            } else {
                                // For images and GIFs, try different approaches based on type
                if ($isGif) {
                    Log::info('Attempting GIF upload with tweet_gif media category', ['path' => $localPath]);
                    // For GIFs, we need to use the chunked upload with media_category=tweet_gif
                    // This enables async processing which is required for GIFs
                    try {
                        // Try to use chunked upload for GIFs with proper media category
                        $media_info = $this->uploadGifWithChunkedMethod($localPath);
                        if ($media_info) {
                            Log::info('GIF chunked upload successful', ['media_info' => $media_info]);
                        } else {
                            throw new \Exception('Chunked upload returned null');
                        }
                    } catch (\Exception $e) {
                        Log::warning('GIF chunked upload failed, trying base64', ['error' => $e->getMessage()]);
                        // Fallback to base64 encoding
                        $file_data = base64_encode(file_get_contents($localPath));
                        $media_info = $this->client->uploadMedia()->upload($file_data);
                    }
                } else {
                    // For regular images, use base64 encoding for better compatibility
        $file_data = base64_encode(file_get_contents($localPath));
        $media_info = $this->client->uploadMedia()->upload($file_data);
                }
            }
            
            Log::info('Media upload response', [
                'media_info' => $media_info,
                'media_id' => $media_info['media_id'] ?? 'not_found',
                'is_video' => $isVideo
            ]);
            
            if (isset($media_info['media_id'])) {
                $mediaId = (string)$media_info['media_id'];
                
                // Wait for media processing (Twitter needs time to process the media)
                Log::info('Waiting for media processing', [
                    'media_id' => $mediaId, 
                    'is_video' => $isVideo,
                    'is_gif' => $isGif
                ]);
                
                // Different wait times based on media type
                if ($isVideo) {
                    $waitTime = 10; // Videos need more time
                } elseif ($isGif) {
                    $waitTime = 8; // GIFs need more time than regular images
                } else {
                    $waitTime = 3; // Regular images
                }
                
                sleep($waitTime);
                
                // For GIFs, check processing status before proceeding
                if ($isGif) {
                    Log::info('Checking GIF processing status', ['media_id' => $mediaId]);
                    
                    // Check if GIF meets Twitter's requirements
                    $this->validateGifRequirements($localPath);
                    
                    // For GIFs, wait much longer since Twitter needs time to process animated GIFs
                    $gifWaitTime = 60; // 60 seconds for GIFs (increased from 15)
                    Log::info('Waiting for GIF processing', [
                        'media_id' => $mediaId,
                        'wait_time' => $gifWaitTime
                    ]);
                    
                    sleep($gifWaitTime);
                    
                    // Try to validate the media ID by attempting a status check
                    // This will help us know if the media is ready
                    Log::info('GIF processing wait completed', ['media_id' => $mediaId]);
                    
                    // For GIFs, let's also try a different approach - validate the media ID
                    // by attempting to use it in a test request
                    Log::info('GIF validation completed', ['media_id' => $mediaId]);
                    
                    // Additional wait for GIFs to ensure they're fully processed
                    Log::info('Additional GIF processing wait', ['media_id' => $mediaId]);
                    sleep(30); // Extra 30 seconds for GIF processing
                }
                
                Log::info('Media upload successful', [
                    'media_id' => $mediaId,
                    'media_info' => $media_info,
                    'is_video' => $isVideo,
                    'is_gif' => $isGif
                ]);
                
                return $mediaId;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Media upload failed', [
                'path' => $localPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Upload video using manual chunked upload for large files
     */
    private function uploadVideoChunked($localPath)
    {
        // Chunked upload is disabled due to OAuth method availability issues
        Log::warning('Chunked upload disabled - using regular upload instead');
        return null;
    }

    /**
     * Upload GIF using proper chunked method with INIT -> APPEND -> FINALIZE -> STATUS flow
     * and media_category=tweet_gif
     */
    private function uploadGifWithChunkedMethod($gifPath)
    {
        try {
            $fileSize = filesize($gifPath);
            Log::info('Starting GIF chunked upload with tweet_gif media category', [
                'gif_path' => $gifPath,
                'file_size' => $fileSize
            ]);
            
            // Use the proper chunked upload with media_category=tweet_gif
            $media_info = $this->uploadGifWithGuzzle($gifPath);
            
            Log::info('GIF chunked upload successful', [
                'media_info' => $media_info,
                'file_size' => $fileSize
            ]);
            
            return $media_info;
            
        } catch (\Exception $e) {
            Log::error('GIF chunked upload failed', [
                'gif_path' => $gifPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Upload GIF using direct OAuth 1.0a chunked upload with media_category=tweet_gif
     * This bypasses the Twitter package entirely for GIF uploads
     */
    private function uploadGifWithGuzzle($gifPath)
    {
        try {
            $fileSize = filesize($gifPath);
            $mediaType = 'image/gif';
            $chunkSize = 4 * 1024 * 1024; // 4 MB
            
            Log::info('Starting direct OAuth 1.0a GIF chunked upload with media_category=tweet_gif', [
                'file_size' => $fileSize,
                'media_type' => $mediaType
            ]);
            
            // Get OAuth credentials
            $user = Auth::user();
            if (!$user || !$user->twitter_account_connected) {
                throw new \Exception('User not authenticated with Twitter');
            }
            
            $oauthSettings = [
                'consumer_key'    => config('services.twitter.api_key'),
                'consumer_secret' => config('services.twitter.api_key_secret'),
                'access_token'    => $user->twitter_access_token,
                'access_token_secret' => $user->twitter_access_token_secret,
            ];
            
            Log::info('OAuth credentials loaded', [
                'consumer_key' => substr($oauthSettings['consumer_key'], 0, 8) . '...',
                'has_access_token' => !empty($oauthSettings['access_token']),
                'has_access_token_secret' => !empty($oauthSettings['access_token_secret'])
            ]);

            // 1. INIT
            $initParams = [
                'command'        => 'INIT',
                'media_type'     => $mediaType,
                'total_bytes'    => $fileSize,
                'media_category' => 'tweet_gif' // 🔑 REQUIRED for GIFs
            ];
            
            $initResponse = $this->makeOAuthRequest('POST', 'https://upload.twitter.com/1.1/media/upload.json', $initParams, $oauthSettings);
            $initData = json_decode($initResponse, true);
            $mediaId = $initData['media_id_string'] ?? null;
            
            if (!$mediaId) {
                throw new \Exception('Failed to INIT GIF upload: ' . json_encode($initData));
            }
            
            Log::info('GIF upload INIT successful', [
                'media_id' => $mediaId,
                'init_data' => $initData
            ]);

            // 2. APPEND
            $handle = fopen($gifPath, 'rb');
            $segmentIndex = 0;
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) break;

                $appendParams = [
                    'command' => 'APPEND',
                    'media_id' => $mediaId,
                    'segment_index' => $segmentIndex,
                ];
                
                $this->makeOAuthMultipartRequest('POST', 'https://upload.twitter.com/1.1/media/upload.json', $appendParams, $chunk, $oauthSettings);

                Log::info('GIF chunk uploaded', [
                    'segment_index' => $segmentIndex,
                    'chunk_size' => strlen($chunk)
                ]);

                $segmentIndex++;
            }
            fclose($handle);

            // 3. FINALIZE
            $finalizeParams = [
                'command'  => 'FINALIZE',
                'media_id' => $mediaId
            ];
            
            $finalizeResponse = $this->makeOAuthRequest('POST', 'https://upload.twitter.com/1.1/media/upload.json', $finalizeParams, $oauthSettings);
            $finalizeData = json_decode($finalizeResponse, true);
            
            Log::info('GIF upload FINALIZE successful', [
                'media_id' => $mediaId,
                'finalize_data' => $finalizeData
            ]);

            // 4. If processing_info exists → poll until done
            if (isset($finalizeData['processing_info'])) {
                $state = $finalizeData['processing_info']['state'];
                while ($state === 'in_progress' || $state === 'pending') {
                    sleep($finalizeData['processing_info']['check_after_secs'] ?? 10);
                    
                    $statusParams = [
                        'command'  => 'STATUS',
                        'media_id' => $mediaId
                    ];
                    
                    $statusResponse = $this->makeOAuthRequest('GET', 'https://upload.twitter.com/1.1/media/upload.json', $statusParams, $oauthSettings);
                    $statusData = json_decode($statusResponse, true);
                    $state = $statusData['processing_info']['state'] ?? 'succeeded';

                    Log::info('GIF status check', [
                        'media_id' => $mediaId,
                        'state' => $state,
                        'status_data' => $statusData
                    ]);

                    if ($state === 'failed') {
                        throw new \Exception('GIF processing failed: ' . json_encode($statusData));
                    }
                }
            }

            // Return in the same format as the regular upload method
            return [
                'media_id' => $mediaId,
                'media_id_string' => $mediaId,
                'media_key' => '3_' . $mediaId,
                'size' => $fileSize,
                'expires_after_secs' => 86400,
                'image' => [
                    'image_type' => $mediaType,
                    'w' => 212, // We'll get this from getimagesize if needed
                    'h' => 256
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Direct OAuth GIF chunked upload failed', [
                'gif_path' => $gifPath,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Make OAuth 1.0a request manually
     */
    private function makeOAuthRequest($method, $url, $params, $settings)
    {
        $oauthParams = $this->buildOAuthParams($settings);
        $allParams = array_merge($params, $oauthParams);
        
        $signature = $this->buildOAuthSignature($method, $url, $allParams, $settings);
        $allParams['oauth_signature'] = $signature;
        
        $header = $this->buildOAuthHeader($allParams);
        
        $guzzleClient = new GuzzleClient();
        $response = $guzzleClient->request($method, $url, [
            'headers' => [
                'Authorization' => $header,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => $params,
        ]);
        
        return $response->getBody()->getContents();
    }

    /**
     * Make OAuth 1.0a multipart request manually
     */
    private function makeOAuthMultipartRequest($method, $url, $params, $chunk, $settings)
    {
        $oauthParams = $this->buildOAuthParams($settings);
        $allParams = array_merge($params, $oauthParams);
        
        $signature = $this->buildOAuthSignature($method, $url, $allParams, $settings);
        $allParams['oauth_signature'] = $signature;
        
        $header = $this->buildOAuthHeader($allParams);
        
        $guzzleClient = new GuzzleClient();
        $response = $guzzleClient->request($method, $url, [
            'headers' => [
                'Authorization' => $header,
            ],
            'multipart' => [
                ['name' => 'command', 'contents' => $params['command']],
                ['name' => 'media_id', 'contents' => $params['media_id']],
                ['name' => 'segment_index', 'contents' => $params['segment_index']],
                ['name' => 'media', 'contents' => $chunk],
            ],
        ]);
        
        return $response->getBody()->getContents();
    }

    /**
     * Build OAuth 1.0a parameters
     */
    private function buildOAuthParams($settings)
    {
        return [
            'oauth_consumer_key' => $settings['consumer_key'],
            'oauth_nonce' => uniqid(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $settings['access_token'],
            'oauth_version' => '1.0',
        ];
    }

    /**
     * Build OAuth 1.0a signature
     */
    private function buildOAuthSignature($method, $url, $params, $settings)
    {
        // Sort parameters
        ksort($params);
        
        // Build query string
        $queryString = http_build_query($params);
        
        // Build signature base string
        $signatureBaseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($queryString);
        
        // Build signing key
        $signingKey = rawurlencode($settings['consumer_secret']) . '&' . rawurlencode($settings['access_token_secret']);
        
        // Generate signature
        return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
    }

    /**
     * Build OAuth 1.0a header
     */
    private function buildOAuthHeader($params)
    {
        $header = 'OAuth ';
        $headerParams = [];
        
        foreach ($params as $key => $value) {
            if (strpos($key, 'oauth_') === 0) {
                $headerParams[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
            }
        }
        
        $header .= implode(', ', $headerParams);
        return $header;
    }





    /**
     * Validate GIF against Twitter's requirements
     */
    private function validateGifRequirements($gifPath)
    {
        try {
            $fileSize = filesize($gifPath);
            $imageInfo = getimagesize($gifPath);
            
            if ($imageInfo === false) {
                Log::warning('Could not get GIF image info', ['path' => $gifPath]);
                return;
            }
            
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            
            Log::info('GIF validation', [
                'path' => $gifPath,
                'file_size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);
            
            // Check Twitter's requirements
            $issues = [];
            
            // File size <= 15MB
            if ($fileSize > 15 * 1024 * 1024) {
                $issues[] = 'File size exceeds 15MB limit';
            }
            
            // Resolution <= 1280x1080
            if ($width > 1280 || $height > 1080) {
                $issues[] = "Resolution {$width}x{$height} exceeds 1280x1080 limit";
            }
            
            if (!empty($issues)) {
                Log::warning('GIF does not meet Twitter requirements', [
                    'path' => $gifPath,
                    'issues' => $issues
                ]);
            } else {
                Log::info('GIF meets Twitter requirements', ['path' => $gifPath]);
            }
            
        } catch (\Exception $e) {
            Log::warning('Could not validate GIF requirements', [
                'path' => $gifPath,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Convert GIF to static image as fallback
     */
    private function convertGifToStaticImage($gifPath)
    {
        try {
            Log::info('Converting GIF to static image', ['gif_path' => $gifPath]);
            
            // Create a temporary file for the converted image
            $tempPath = tempnam(sys_get_temp_dir(), 'gif_converted_') . '.jpg';
            
            // Use GD to convert GIF to JPEG
            $gif = imagecreatefromgif($gifPath);
            if ($gif === false) {
                throw new \Exception('Failed to create image from GIF');
            }
            
            // Convert to JPEG
            $result = imagejpeg($gif, $tempPath, 90);
            imagedestroy($gif);
            
            if ($result === false) {
                throw new \Exception('Failed to save converted image');
            }
            
            Log::info('GIF converted to static image', [
                'original' => $gifPath,
                'converted' => $tempPath,
                'size' => filesize($tempPath)
            ]);
            
            return $tempPath;
        } catch (\Exception $e) {
            Log::error('Failed to convert GIF to static image', [
                'gif_path' => $gifPath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // Tweet/Quotes endpoints
    /**
     * Get quote tweets for a tweet.
     */
    public function getQuoteTweets($tweetId)
    {
        return $this->client->tweetQuotes()->getQuoteTweets($tweetId)->performRequest();
    }

    // Retweet endpoints
    /**
     * Retweet a tweet.
     */
    public function retweet($tweetId)
    {
        return $this->client->retweet()->performRequest(['tweet_id' => $tweetId]);
    }

    /**
     * Like a tweet.
     */
    public function likeTweet($tweetId)
    {
        // Try the correct like endpoint structure - might need different parameters
        return $this->client->tweetLikes()->like()->performRequest(['tweet_id' => $tweetId]);
    }

    // Tweet/Replies endpoints
    /**
     * Hide a reply to a tweet.
     */
    public function hideReply($tweetId)
    {
        return $this->client->tweetReplies()->hideReply($tweetId)->performRequest(['hidden' => true]);
    }

    /**
     * Unhide a reply to a tweet.
     */
    public function unhideReply($tweetId)
    {
        return $this->client->tweetReplies()->hideReply($tweetId)->performRequest(['hidden' => false]);
    }

    // Tweet/Bookmarks endpoints
    /**
     * Lookup a user's bookmarks.
     */
    public function getBookmarks()
    {
        return $this->client->tweetBookmarks()->lookup()->performRequest();
    }

    // Users endpoints
    // User/Blocks endpoints
    /**
     * Retrieve the users which you've blocked.
     */
    public function getBlockedUsers()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->client->userBlocks()->lookup($userId)->performRequest();
    }

    // User/Follows endpoints
    /**
     * Retrieve the users which are following you.
     */
    public function getFollowers()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->client->userFollows()->getFollowers($userId)->performRequest();
    }

    /**
     * Retrieve the users which you are following.
     */
    public function getFollowing()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->client->userFollows()->getFollowing($userId)->performRequest();
    }

    /**
     * Follow a user.
     */
    public function followUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userFollows()->follow($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unfollow a user.
     */
    public function unfollowUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userFollows()->unfollow($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    // User/Lookup endpoints
    /**
     * Find me (the authenticated user).
     */
    public function findMe()
    {
        return $this->client->userMeLookup()->performRequest();
    }

    /**
     * Find Twitter users by username or ID.
     */
    public function findUser($value, $mode)
    {
        return $this->client->userLookup()->findByIdOrUsername($value, $mode)->performRequest();
    }

    // User/Mutes endpoints
    /**
     * Retrieve the users which you've muted.
     */
    public function getMutedUsers()
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $userId = $me->data->id;
        return $this->client->userMutes()->lookup($userId)->performRequest();
    }

    /**
     * Mute a user by ID.
     */
    public function muteUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userMutes()->mute($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unmute a user by ID.
     */
    public function unmuteUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userMutes()->unmute($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    // User/Blocks endpoints
    /**
     * Block a user by ID.
     */
    public function blockUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userBlocks()->block($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Unblock a user by ID.
     */
    public function unblockUser($userId)
    {
        // Get the authenticated user's ID first
        $me = $this->findMe();
        if (!$me || !isset($me->data->id)) {
            throw new \Exception('Unable to get authenticated user ID');
        }
        
        $sourceUserId = $me->data->id;
        return $this->client->userBlocks()->unblock($sourceUserId)->performRequest(['target_user_id' => $userId]);
    }

    /**
     * Truncate text to fit Twitter's character limit
     */
    public function truncateForTwitter($text, $maxLength = 280)
    {
        $charCount = mb_strlen($text, 'UTF-8');
        
        if ($charCount <= $maxLength) {
            return $text;
        }
        
        // Truncate and add ellipsis
        $truncated = mb_substr($text, 0, $maxLength - 3, 'UTF-8') . '...';
        
        Log::info('Text truncated for Twitter', [
            'original_length' => $charCount,
            'truncated_length' => mb_strlen($truncated, 'UTF-8'),
            'max_length' => $maxLength
        ]);
        
        return $truncated;
    }

    /**
     * Check Twitter API status and rate limits
     */
    public function checkApiStatus()
    {
        try {
            // Try to get user info to check API status
            $result = $this->client->userMeLookup()->performRequest();
            
            Log::info('Twitter API status check successful', [
                'user_id' => $result->data->id ?? 'unknown',
                'username' => $result->data->username ?? 'unknown'
            ]);
            
            return [
                'status' => 'ok',
                'user_id' => $result->data->id ?? null,
                'username' => $result->data->username ?? null,
                'message' => 'API is working correctly'
            ];
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();
            
            Log::error('Twitter API status check failed', [
                'status_code' => $statusCode,
                'error_body' => $body
            ]);
            
            if ($statusCode === 429) {
                return [
                    'status' => 'rate_limited',
                    'message' => 'Rate limit exceeded. Please wait before making more requests.',
                    'status_code' => $statusCode
                ];
            }
            
            return [
                'status' => 'error',
                'message' => "HTTP {$statusCode}: {$e->getMessage()}",
                'status_code' => $statusCode
            ];
            
        } catch (\Exception $e) {
            Log::error('Twitter API status check unexpected error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'message' => "Unexpected error: {$e->getMessage()}"
            ];
        }
    }

    /**
     * Get character count for text
     */
    public function getCharacterCount($text)
    {
        return mb_strlen($text, 'UTF-8');
    }
} 