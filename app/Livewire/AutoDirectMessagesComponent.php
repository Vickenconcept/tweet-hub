<?php

namespace App\Livewire;

use App\Models\AutoDm;
use App\Models\User;
use App\Services\ChatGptService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class AutoDirectMessagesComponent extends Component
{
    // Interaction-based auto DM properties
    public bool $interactionAutoDmEnabled = false;
    public string $interactionDmTemplate = '';
    public int $interactionDailyLimit = 50;
    public bool $monitorLikes = true;
    public bool $monitorRetweets = true;
    public bool $monitorReplies = true;
    public bool $monitorQuotes = true;

    // AI generation properties
    public bool $generatingTemplate = false;
    public string $aiPrompt = '';

    // Sent DMs tracking
    public $sentDms = [];
    public $loadingSentDms = false;
    public $sentDmsPage = 1;
    public $perPage = 20;

    public function mount()
    {
        // Load interaction auto DM settings
        $user = Auth::user();
        if ($user) {
            $this->interactionAutoDmEnabled = (bool) ($user->interaction_auto_dm_enabled ?? false);
            $this->interactionDmTemplate = (string) ($user->interaction_auto_dm_template ?? "Hey! Thanks for engaging with my tweet. I'd love to connect!");
            $this->interactionDailyLimit = (int) ($user->interaction_auto_dm_daily_limit ?? 50);
            
            // Auto-load sent DMs
            $this->loadSentDms();
        }
    }

    public function saveInteractionSettings()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        User::where('id', $user->id)->update([
            'interaction_auto_dm_enabled' => $this->interactionAutoDmEnabled,
            'interaction_auto_dm_template' => $this->interactionDmTemplate,
            'interaction_auto_dm_daily_limit' => $this->interactionDailyLimit,
        ]);

        session()->flash('message', 'Auto DM settings saved!');
        
        Log::info('📩 Interaction Auto DM settings saved', [
            'user_id' => $user->id,
            'enabled' => $this->interactionAutoDmEnabled,
            'daily_limit' => $this->interactionDailyLimit,
        ]);
    }

    public function generateDmTemplate()
    {
        $this->generatingTemplate = true;

        try {
            $user = Auth::user();
            $prompt = trim($this->aiPrompt);

            // Build a smart prompt if user provided context, otherwise use a generic one
            if (empty($prompt)) {
                $prompt = "Write a friendly, professional direct message template for Twitter/X when someone interacts with your tweets. 
                The message should be:
                - Warm and welcoming
                - Short and concise (not more than 179 characters)
                - Professional but personable
                - Not overly salesy or pushy
                - Thank them for engaging with your content
                
                Write just the message text, no explanations or extra text.";
            } else {
                $prompt = "Write a friendly, professional direct message template for Twitter/X based on this context: {$prompt}
                
                The message should be:
                - Warm and welcoming
                - Short and concise (not more than 179 characters)
                - Professional but personable
                - Not overly salesy or pushy
                - Thank them for engaging with your content
                
                Write just the message text, no explanations, no quotes, no extra text. Just the direct message content.";
            }

            $chatGptService = new ChatGptService();
            $generatedText = $chatGptService->generateContent($prompt);

            if ($generatedText && trim($generatedText) !== '') {
                // Clean up the generated text (remove quotes, extra whitespace, etc.)
                $cleanedText = trim($generatedText);
                // Remove surrounding quotes if present
                $cleanedText = trim($cleanedText, '"\'');
                // Remove "DM:" or "Message:" prefixes if AI added them
                $cleanedText = preg_replace('/^(DM|Message|Direct Message):\s*/i', '', $cleanedText);
                $cleanedText = trim($cleanedText);

                if (mb_strlen($cleanedText) > 0) {
                    $this->interactionDmTemplate = $cleanedText;
                    session()->flash('message', 'DM template generated successfully! You can edit it if needed.');
                    
                    Log::info('📩 AutoDirectMessages: DM template generated via AI', [
                        'user_id' => $user->id ?? null,
                        'prompt_length' => mb_strlen($prompt),
                        'generated_length' => mb_strlen($cleanedText),
                    ]);
                } else {
                    session()->flash('error', 'Generated template was empty. Please try again with more specific context.');
                }
            } else {
                session()->flash('error', 'Failed to generate template. Please try again.');
            }
        } catch (\Exception $e) {
            Log::error('📩 AutoDirectMessages: AI template generation failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to generate template: ' . $e->getMessage());
        } finally {
            $this->generatingTemplate = false;
            $this->aiPrompt = ''; // Clear the prompt after generation
        }
    }

    public function loadSentDms()
    {
        $this->loadingSentDms = true;
        $user = Auth::user();
        
        if (!$user) {
            $this->loadingSentDms = false;
            return;
        }

        try {
            $this->sentDms = AutoDm::where('user_id', $user->id)
                ->where('source_type', 'interaction')
                ->where('status', 'sent')
                ->orderBy('sent_at', 'desc')
                ->limit($this->perPage)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Failed to load sent DMs', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $this->sentDms = [];
        } finally {
            $this->loadingSentDms = false;
        }
    }

    public function render()
    {
        return view('livewire.auto-direct-messages-component');
    }
}
