<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;


class ChatGptService
{
    protected $httpClient;
    protected $apiKey;


    public function __construct()
    {
        $this->httpClient = new Client();
        $this->apiKey = env('OPENAI_API_KEY');
    }

    public function generateContent($inputData)
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $maxRetries = 3;
        $retryDelay = 5; // seconds

        for ($retry = 0; $retry < $maxRetries; $retry++) {
            try {
                $response = $this->httpClient->post($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'gpt-4o-mini',
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are a knowledgeable assistant that provides detailed explanations about topics.'],
                            ['role' => 'user', 'content' => $inputData],
                        ],
                        'temperature' => 0.2,
                    ],
                ]);

                $content = json_decode($response->getBody(), true)['choices'][0]['message']['content'];

                return $content;
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 429) {
                    if ($retry < $maxRetries - 1) {
                        Log::info("Rate limit exceeded. Retrying in {$retryDelay} seconds.");
                        sleep($retryDelay);
                    } else {
                        Log::error("API request failed: Rate limit exceeded after retries.");
                    }
                } else {
                    Log::error("API request failed: " . $e->getMessage());
                    break;
                }
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'Could not resolve host') !== false) {
                    Log::error('cURL error: Could not resolve host');
                    return  'You have an unstable network';
                } elseif (strpos($e->getMessage(), 'cURL error 35') !== false) {

                    Log::error('cURL SSL connection error: ' . $e->getMessage());
                    return 'Connection error,Please try again later.';
                }


                throw $e;
            }
        }

        return null;
    }

    /**
     * Generate an image using DALL-E
     */
    public function generateImage($prompt, $size = '1024x1024', $style = 'natural')
    {
        $url = 'https://api.openai.com/v1/images/generations';
        $maxRetries = 3;
        $retryDelay = 5;

        for ($examinedAttempt = 0; $examinedAttempt < $maxRetries; $examinedAttempt++) {
            try {
                $response = $this->httpClient->post($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'dall-e-3',
                        'prompt' => $prompt,
                        'n' => 1,
                        'size' => $size,
                        'quality' => 'standard',
                        'style' => $style,
                    ],
                    'timeout' => 120, // 2 minutes timeout for image generation
                ]);

                $body = json_decode($response->getBody(), true);
                
                if (isset($body['data'][0]['url'])) {
                    Log::info('Image generated successfully', [
                        'prompt' => $prompt,
                        'url' => $body['data'][0]['url']
                    ]);
                    return $body['data'][0]['url'];
                } else {
                    Log::error('Unexpected response format from DALL-E API', ['response' => $body]);
                    return null;
                }
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                $errorBody = $e->getResponse()->getBody()->getContents();
                
                Log::error('DALL-E API error', [
                    'status_code' => $statusCode,
                    'error_body' => $errorBody,
                    'attempt' => $examinedAttempt
                ]);
                
                if ($statusCode === 429 && $examinedAttempt < $maxRetries - 1) {
                    Log::info("Rate limit exceeded. Retrying in {$retryDelay} seconds.");
                    sleep($retryDelay);
                } else {
                    // Parse error for user-friendly message
                    $errorData = json_decode($errorBody, true);
                    $errorMessage = 'Failed to generate image';
                    
                    if (isset($errorData['error'])) {
                        $apiError = $errorData['error'];
                        
                        // Handle specific error types
                        if (isset($apiError['code']) && $apiError['code'] === 'billing_hard_limit_reached') {
                            $errorMessage = 'OpenAI billing limit reached. Please add credits to your OpenAI account or check your usage limits.';
                        } elseif (isset($apiError['message'])) {
                            $errorMessage = 'OpenAI API Error: ' . $apiError['message'];
                        }
                    } else {
                        $errorMessage = 'Failed to generate image: ' . $errorBody;
                    }
                    
                    throw new \Exception($errorMessage);
                }
            } catch (\Exception $e) {
                Log::error('Image generation failed', [
                    'error' => $e->getMessage(),
                    'prompt' => $prompt
                ]);
                
                if ($examinedAttempt < $maxRetries - 1) {
                    sleep($retryDelay);
                } else {
                    throw $e;
                }
            }
        }

        return null;
    }
}
