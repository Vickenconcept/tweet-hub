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
}
