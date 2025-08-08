<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ChatGptService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class DailyPostIdeasComponent extends Component
{
    public $ideas = [];
    public $loading = false;
    public $errorMessage = '';
    public $successMessage = '';
    public $selectedIdea = '';
    public $topic = '';
    public $niche = '';
    public $activeTab = 'generate';
    public $currentPage = 1;
    public $perPage = 10;
    public $favorites = [];

    protected $chatGptService;

    public function boot(ChatGptService $chatGptService)
    {
        $this->chatGptService = $chatGptService;
    }

    public function mount()
    {
        $this->loadFavorites();
    }

    public function generateIdeas()
    {
        $this->validate([
            'topic' => 'required|min:3|max:100',
            'niche' => 'required|min:3|max:100',
        ]);

        $this->loading = true;
        $this->errorMessage = '';
        $this->successMessage = '';

        try {
            $prompt = "Generate 10 complete, ready-to-post daily posts for a {$this->niche} account about {$this->topic}. 
                      Each post should be:
                      - A complete, human-written post that someone would actually post on social media
                      - Engaging, conversational, and authentic in tone
                      - Include relevant hashtags naturally within the content
                      - Be between 150-279 words
                      - Have a compelling hook and clear value for the audience
                      - Feel like it was written by a real person, not an AI
                      
                      Write these as actual posts someone would publish, not outlines or ideas.
                      Format as a numbered list with complete posts.";

            $response = $this->chatGptService->generateContent($prompt);
            
            if ($response) {
                $this->ideas = $this->parseIdeas($response);
                $this->successMessage = 'Daily post ideas generated successfully! Count: ' . count($this->ideas);
            } else {
                $this->errorMessage = 'Failed to generate ideas. Please try again.';
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Error generating ideas: ' . $e->getMessage();
        }

        $this->loading = false;
    }

    private function parseIdeas($response)
    {
        $lines = explode("\n", $response);
        $ideas = [];
        $currentIdea = '';
        $ideaNumber = 0;
        $inIdea = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check if this is a new numbered post (**1.**, **2.**, etc.)
            if (preg_match('/^\*\*\d+\.\*\*\s*$/', $line)) {
                // Save the previous idea if we have one
                if (!empty($currentIdea) && $ideaNumber > 0) {
                    $ideas[] = trim($currentIdea);
                }
                
                // Start new idea
                $currentIdea = '';
                $ideaNumber++;
                $inIdea = true;
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
                $inIdea = true;
            }
            // Continue building the current idea (any non-empty line that's not a new numbered item)
            else if (!empty($line) && $inIdea && 
                     !preg_match('/^(?:###\s*)?\d+\./', $line) && 
                     !preg_match('/^\*\*\d+\.\*\*/', $line) &&
                     !preg_match('/^---$/', $line) &&
                     !preg_match('/^Sure! Here are/', $line) &&
                     !preg_match('/^Feel free to adjust/', $line)) {
                $currentIdea .= "\n" . $line;
            }
        }
        
        // Add the last idea
        if (!empty($currentIdea) && $ideaNumber > 0) {
            $ideas[] = trim($currentIdea);
        }
        
        return array_slice($ideas, 0, 10); // Ensure max 10 ideas
    }

    public function selectIdea($index)
    {
        if (isset($this->ideas[$index])) {
            $this->selectedIdea = $this->ideas[$index];
            $this->dispatch('idea-selected', idea: $this->selectedIdea);
        }
    }

    public function editInChat($index)
    {
        // Calculate the actual index in the full ideas array
        $actualIndex = ($this->currentPage - 1) * $this->perPage + $index;
        
        if (isset($this->ideas[$actualIndex])) {
            $idea = $this->ideas[$actualIndex];
            $this->dispatch('edit-idea-in-chat', idea: $idea);
            $this->successMessage = 'Idea sent to chat for editing!';
        }
    }

    public function clearIdeas()
    {
        $this->ideas = [];
        $this->selectedIdea = '';
        $this->topic = '';
        $this->niche = '';
        $this->errorMessage = '';
        $this->successMessage = '';
    }

         public function setTab($tab)
     {
         $this->activeTab = $tab;
         $this->currentPage = 1;
         
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
        // Calculate the actual index in the full ideas array
        $actualIndex = ($this->currentPage - 1) * $this->perPage + $index;
        
        if (isset($this->ideas[$actualIndex])) {
            $idea = $this->ideas[$actualIndex];
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

    public function nextPage()
    {
        $totalPages = ceil(count($this->ideas) / $this->perPage);
        if ($this->currentPage < $totalPages) {
            $this->currentPage++;
        }
    }

    public function previousPage()
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function getPaginatedIdeas()
    {
        $start = ($this->currentPage - 1) * $this->perPage;
        return array_slice($this->ideas, $start, $this->perPage);
    }

    public function render()
    {
        $paginatedIdeas = $this->getPaginatedIdeas();
        $totalIdeas = count($this->ideas);
        
        // Debug information
        if ($totalIdeas > 0) {
            $this->successMessage .= ' | Paginated: ' . count($paginatedIdeas) . ' | Total: ' . $totalIdeas;
        }
        
        return view('livewire.daily-post-ideas-component', [
            'paginatedIdeas' => $paginatedIdeas,
            'totalPages' => ceil($totalIdeas / $this->perPage),
            'totalIdeas' => $totalIdeas,
        ]);
    }
} 