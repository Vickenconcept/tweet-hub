<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ChatGptService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

class GeneratePostIdeasComponent extends Component
{
    public $generatedIdeas = [];
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $selectedIdea = '';
    public $prompt = '';
    public $ideaType = 'general';
    public $tone = 'professional';

    public $activeTab = 'generate';

    public $favorites = [];
    public $hasCachedIdeas = false;
    public $cacheKey = '';

    protected $chatGptService;

    public function boot(ChatGptService $chatGptService)
    {
        $this->chatGptService = $chatGptService;
    }

    public function mount()
    {
        $this->loadFavorites();
        $this->checkCachedIdeas();
    }

    public function checkCachedIdeas()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $this->cacheKey = "generated_ideas_{$user->id}";
        $cachedIdeas = Cache::get($this->cacheKey);

        if ($cachedIdeas && !empty($cachedIdeas)) {
            $this->generatedIdeas = $cachedIdeas;
            $this->hasCachedIdeas = true;
            $this->successMessage = 'Generated ideas loaded from cache!';
        } else {
            $this->hasCachedIdeas = false;
        }
    }

    public function generatePostIdeas()
    {
        $this->validate([
            'prompt' => 'required|min:10|max:500',
        ]);

        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $fullPrompt = $this->buildPrompt();
            $response = $this->chatGptService->generateContent($fullPrompt);
            
            if ($response) {
                $this->generatedIdeas = $this->parseGeneratedIdeas($response);
                
                // Cache the generated ideas
                $user = Auth::user();
                if ($user) {
                    $this->cacheKey = "generated_ideas_{$user->id}";
                    Cache::put($this->cacheKey, $this->generatedIdeas, now()->addDays(7)); // Cache for 7 days
                    $this->hasCachedIdeas = true;
                }
                
                $this->successMessage = 'Post ideas generated successfully! Count: ' . count($this->generatedIdeas);
            } else {
                $this->errorMessage = 'Failed to generate post ideas. Please try again.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Error generating post ideas: ' . $e->getMessage();
        }

        $this->loading = false;
    }

    private function buildPrompt()
    {
        $basePrompt = "Generate 12 complete, ready-to-post social media posts based on this prompt: '{$this->prompt}'";
        
        $typeContext = match($this->ideaType) {
            'educational' => 'Write educational posts that teach something valuable with clear explanations, actionable tips, or insights that help the audience learn.',
            'entertaining' => 'Create entertaining posts with humor, interesting stories, fun facts, or creative angles that engage and delight the audience.',
            'inspirational' => 'Write inspirational posts with motivational stories, uplifting messages, or thought-provoking content that inspires action.',
            'promotional' => 'Create promotional posts that highlight benefits, solutions, or offers while providing genuine value to the audience.',
            default => 'Write engaging posts that provide value and connect with the audience.'
        };

        $toneContext = match($this->tone) {
            'casual' => 'Use a casual, friendly, conversational tone that feels natural and approachable.',
            'professional' => 'Maintain a professional tone with expertise and credibility while being accessible.',
            'humorous' => 'Include humor, wit, and lighthearted elements that make the content enjoyable.',
            'formal' => 'Use a formal, authoritative tone that conveys expertise and trustworthiness.',
            default => 'Use a balanced, engaging tone that connects with the audience.'
        };

        $detailedPrompt = "Each post should be:
        - A complete, human-written post that someone would actually publish
        - Engaging, conversational, and authentic in tone
        - Include relevant hashtags naturally within the content
        - Be between 150-279 words (optimized for Twitter/X character limits)
        - Have a compelling hook and clear value for the audience
        - Feel like it was written by a real person, not an AI
        
        Write these as actual posts someone would publish, not outlines or ideas.";

        return "{$basePrompt}. {$typeContext} {$toneContext}. {$detailedPrompt} Format as a numbered list with complete posts.";
    }

    private function parseGeneratedIdeas($response)
    {
        $lines = explode("\n", $response);
        $ideas = [];
        $currentIdea = '';
        $ideaNumber = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check if this is a new numbered post (1., ### 1., **1.**, etc.)
            if (preg_match('/^(?:###\s*)?\*\*\d+\.\*\*\s*(.+)$/', $line, $matches)) {
                // Save the previous idea if we have one
                if (!empty($currentIdea) && $ideaNumber > 0) {
                    $ideas[] = trim($currentIdea);
                }
                
                // Start new idea
                $currentIdea = $matches[1];
                $ideaNumber++;
            }
            // Also check for regular numbered format (1. or ### 1.)
            else if (preg_match('/^(?:###\s*)?\d+\.\s*(.+)$/', $line, $matches)) {
                // Save the previous idea if we have one
                if (!empty($currentIdea) && $ideaNumber > 0) {
                    $ideas[] = trim($currentIdea);
                }
                
                // Start new idea
                $currentIdea = $matches[1];
                $ideaNumber++;
            }
            // Continue building the current idea (any non-empty line that's not a new numbered item)
            else if (!empty($line) && $ideaNumber > 0 && 
                     !preg_match('/^(?:###\s*)?\d+\./', $line) && 
                     !preg_match('/^(?:###\s*)?\*\*\d+\.\*\*/', $line) &&
                     !preg_match('/^---$/', $line)) {
                $currentIdea .= "\n" . $line;
            }
        }
        
        // Add the last idea
        if (!empty($currentIdea) && $ideaNumber > 0) {
            $ideas[] = trim($currentIdea);
        }
        
        return array_slice($ideas, 0, 12); // Ensure max 12 ideas
    }

    public function selectIdea($index)
    {
        if (isset($this->generatedIdeas[$index])) {
            $this->selectedIdea = $this->generatedIdeas[$index];
            $this->dispatch('post-idea-selected', idea: $this->selectedIdea);
        }
    }

    public function editInChat($index)
    {
        // Calculate the actual index in the full ideas array
        $actualIndex = ($this->currentPage - 1) * $this->perPage + $index;
        
        if (isset($this->generatedIdeas[$actualIndex])) {
            $idea = $this->generatedIdeas[$actualIndex];
            $this->dispatch('edit-idea-in-chat', idea: $idea);
            $this->successMessage = 'Idea sent to chat for editing!';
        }
    }

    public function clearIdeas()
    {
        $this->generatedIdeas = [];
        $this->selectedIdea = '';
        $this->prompt = '';
        $this->errorMessage = '';
        $this->successMessage = '';
        $this->hasCachedIdeas = false;
        
        // Clear the cache
        if ($this->cacheKey) {
            Cache::forget($this->cacheKey);
        }
        
        $this->successMessage = 'All ideas cleared and cache removed!';
    }

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        
        if ($tab === 'favorites') {
            $this->dispatch('render-favorites');
        }
    }

    public function loadFavorites()
    {
        $this->favorites = [];
        if (request()->hasHeader('X-Livewire')) {
            // This will be handled by JavaScript
            return;
        }
    }

    public function toggleFavorite($index)
    {
        if (isset($this->generatedIdeas[$index])) {
            $idea = $this->generatedIdeas[$index];
            $ideaId = md5($idea); // Create unique ID for the idea
            
            $this->dispatch('toggle-favorite', [
                'idea' => $idea,
                'ideaId' => $ideaId,
                'action' => 'toggle'
            ]);
            
            $this->successMessage = 'Favorite status updated!';
        }
    }

    public function removeFavorite($ideaId)
    {
        $this->dispatch('remove-favorite', [
            'ideaId' => $ideaId
        ]);
        
        $this->successMessage = 'Idea removed from favorites!';
    }

    public function render()
    {
        return view('livewire.generate-post-ideas-component', [
            'totalIdeas' => count($this->generatedIdeas),
        ]);
    }
}