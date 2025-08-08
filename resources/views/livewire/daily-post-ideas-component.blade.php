<div class="p-6 bg-white rounded-lg shadow-md" x-data="dailyPostIdeas()">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Daily Post Ideas</h2>
        <button wire:click="clearIdeas" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
            Clear All
        </button>
    </div>

    <!-- Tabs -->
    <div class="flex space-x-1 mb-6 bg-gray-100 p-1 rounded-lg">
        <button wire:click="setTab('generate')" 
                class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'generate' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
            Generate Ideas
        </button>
                 <button wire:click="setTab('favorites')" 
                 x-on:click="onTabChange()"
                 class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'favorites' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
             Favorites
         </button>
    </div>

    <!-- Success/Error Messages -->
    @if($successMessage)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <div class="flex items-center">
                <i class="bx bx-check-circle text-xl mr-2"></i>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            {{ $errorMessage }}
        </div>
    @endif

    <!-- Input Form -->
    @if($activeTab === 'generate')
        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="topic" class="block text-sm font-medium text-gray-700 mb-2">Topic</label>
                    <input wire:model="topic" type="text" id="topic" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., Digital Marketing, Fitness, Technology">
                </div>
                <div>
                    <label for="niche" class="block text-sm font-medium text-gray-700 mb-2">Niche</label>
                    <input wire:model="niche" type="text" id="niche" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="e.g., B2B, Personal Trainer, Startup">
                </div>
            </div>
            <button wire:click="generateIdeas" 
                    wire:loading.attr="disabled"
                    class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <span wire:loading.remove>Generate Daily Ideas</span>
                <span wire:loading>Generating...</span>
            </button>
        </div>
    @endif

    <!-- Generated Ideas -->
    @if($activeTab === 'generate' && count($paginatedIdeas) > 0)
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900">Generated Ideas</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($paginatedIdeas as $index => $idea)
                    @php
                        $actualIndex = ($currentPage - 1) * $perPage + $index;
                        $ideaId = md5($idea);
                    @endphp
                    <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-start justify-between mb-3">
                            <span class="inline-block w-6 h-6 bg-blue-100 text-blue-600 text-sm font-medium rounded-full text-center">
                                {{ $actualIndex + 1 }}
                            </span>
                                                         <button class="text-yellow-500 hover:text-yellow-600 transition-colors"
                                     x-on:click="toggleFavoriteFromButton()"
                                     data-idea-id="{{ $ideaId }}"
                                     data-idea-content="{{ htmlspecialchars($idea, ENT_QUOTES, 'UTF-8') }}">
                                <i class="bx bx-star text-xl" x-bind:class="{ 'bx-star': !isFavorite('{{ $ideaId }}'), 'bxs-star': isFavorite('{{ $ideaId }}') }"></i>
                            </button>
                        </div>
                        <p class="text-gray-800 mb-4" style="display: -webkit-box; -webkit-line-clamp: 8; -webkit-box-orient: vertical; overflow: hidden;">{{ $idea }}</p>
                        <div class="flex gap-2">
                            <button wire:click="editInChat({{ $actualIndex }})" 
                                    class="w-full px-3 py-2 text-sm font-medium text-green-600 bg-green-100 rounded-lg hover:bg-green-200 transition-colors">
                                <i class="bx bx-edit mr-1"></i> Edit in Chat
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($totalPages > 1)
                <div class="flex items-center justify-between mt-6">
                    <div class="text-sm text-gray-700">
                        Showing {{ ($currentPage - 1) * $perPage + 1 }} to {{ min($currentPage * $perPage, $totalIdeas) }} of {{ $totalIdeas }} ideas
                    </div>
                    <div class="flex space-x-2">
                        <button wire:click="previousPage" 
                                wire:loading.attr="disabled"
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                                {{ $currentPage <= 1 ? 'disabled' : '' }}>
                            Previous
                        </button>
                        <span class="px-3 py-2 text-sm font-medium text-gray-700">
                            Page {{ $currentPage }} of {{ $totalPages }}
                        </span>
                        <button wire:click="nextPage" 
                                wire:loading.attr="disabled"
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
                                {{ $currentPage >= $totalPages ? 'disabled' : '' }}>
                            Next
                        </button>
                    </div>
                </div>
            @endif
                 </div>
     @elseif($activeTab === 'generate' && !$loading)
         <div class="text-center py-8 text-gray-500">
             <i class="bx bx-lightbulb text-4xl mb-4"></i>
             <p>Enter a topic and niche to generate daily post ideas</p>
         </div>
     @endif

         <!-- Favorites Tab -->
     @if($activeTab === 'favorites')
         <div class="space-y-4">
             <div class="flex items-center justify-between">
                 <h3 class="text-lg font-semibold text-gray-900">Favorite Ideas</h3>
                 <button x-on:click="loadFavorites(); renderFavorites();" 
                         class="px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-lg hover:bg-blue-200 transition-colors">
                     <i class="bx bx-refresh mr-1"></i> Refresh
                 </button>
             </div>
             <div id="favorites-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                 <!-- Favorites will be loaded here via JavaScript -->
             </div>
             <div id="no-favorites" class="text-center py-8 text-gray-500" style="display: none;">
                 <i class="bx bx-star text-4xl mb-4"></i>
                 <p>No favorite ideas yet</p>
                 <p class="text-sm">Star ideas from the Generate Ideas tab to save them here</p>
             </div>
         </div>
     @endif

    <!-- Loading State -->
    @if($loading)
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="mt-2 text-gray-600">Generating ideas...</p>
        </div>
    @endif
</div>

<script>
function dailyPostIdeas() {
    return {
        favorites: [],
        activeTab: '{{ $activeTab }}',
        
                 init() {
             this.loadFavorites();
             this.renderFavorites();
             // Make instance globally available
             window.dailyPostIdeasInstance = this;
             console.log('DailyPostIdeas initialized, favorites:', this.favorites);
             
             // Watch for tab changes
             this.$watch('activeTab', (value) => {
                 if (value === 'favorites') {
                     console.log('Active tab changed to favorites');
                     this.loadFavorites();
                     this.renderFavorites();
                 }
             });
         },
        
                 loadFavorites() {
             const stored = localStorage.getItem('dailyPostIdeas_favorites');
             this.favorites = stored ? JSON.parse(stored) : [];
             console.log('Loaded from localStorage:', this.favorites);
         },
        
                 saveFavorites() {
             localStorage.setItem('dailyPostIdeas_favorites', JSON.stringify(this.favorites));
             console.log('Saved to localStorage:', this.favorites);
         },
        
                         toggleFavorite(ideaId, idea) {
            console.log('toggleFavorite called with:', ideaId, idea);
            const existingIndex = this.favorites.findIndex(fav => fav.id === ideaId);
            
            if (existingIndex >= 0) {
                // Remove from favorites
                this.favorites.splice(existingIndex, 1);
                console.log('Removed from favorites');
            } else {
                // Add to favorites
                this.favorites.push({
                    id: ideaId,
                    content: idea,
                    addedAt: new Date().toISOString()
                });
                console.log('Added to favorites');
            }
            
            this.saveFavorites();
            this.renderFavorites();
            console.log('Current favorites:', this.favorites);
        },
        
        toggleFavoriteFromButton() {
            const ideaId = this.$el.getAttribute('data-idea-id');
            const ideaContent = this.$el.getAttribute('data-idea-content');
            console.log('toggleFavoriteFromButton called with:', ideaId, ideaContent);
            this.toggleFavorite(ideaId, ideaContent);
        },
        
        isFavorite(ideaId) {
            return this.favorites.some(fav => fav.id === ideaId);
        },
        
        removeFavorite(ideaId) {
            const index = this.favorites.findIndex(fav => fav.id === ideaId);
            if (index >= 0) {
                this.favorites.splice(index, 1);
                this.saveFavorites();
                this.renderFavorites();
            }
        },
        
                 renderFavorites() {
             console.log('renderFavorites called, favorites:', this.favorites);
             const container = document.getElementById('favorites-container');
             const noFavorites = document.getElementById('no-favorites');
             
             console.log('Container found:', !!container);
             console.log('No favorites element found:', !!noFavorites);
             
             if (!container) {
                 console.log('Container not found, returning');
                 return;
             }
             
             if (this.favorites.length === 0) {
                 console.log('No favorites, showing empty state');
                 container.innerHTML = '';
                 if (noFavorites) noFavorites.style.display = 'block';
                 return;
             }
             
             console.log('Rendering favorites, count:', this.favorites.length);
             if (noFavorites) noFavorites.style.display = 'none';
             
             container.innerHTML = this.favorites.map((favorite, index) => `
                 <div class="p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                     <div class="flex items-start justify-between mb-3">
                         <span class="inline-block w-6 h-6 bg-yellow-100 text-yellow-600 text-sm font-medium rounded-full text-center">
                             ${index + 1}
                         </span>
                         <button onclick="window.dailyPostIdeasInstance.removeFavorite('${favorite.id}')" 
                                 class="text-red-500 hover:text-red-600 transition-colors">
                             <i class="bx bx-trash text-xl"></i>
                         </button>
                     </div>
                     <p class="text-gray-800 mb-4" style="display: -webkit-box; -webkit-line-clamp: 8; -webkit-box-orient: vertical; overflow: hidden;">${favorite.content}</p>
                     <div class="flex gap-2">
                                                   <button onclick="window.dailyPostIdeasInstance.editFavoriteInChatFromButton(this)" 
                                  data-content="${favorite.content.replace(/"/g, '&quot;')}"
                                  class="w-full px-3 py-2 text-sm font-medium text-green-600 bg-green-100 rounded-lg hover:bg-green-200 transition-colors">
                             <i class="bx bx-edit mr-1"></i> Edit in Chat
                         </button>
                     </div>
                 </div>
             `).join('');
         },
        
                         editFavoriteInChat(content) {
            console.log('editFavoriteInChat called with:', content);
            // Dispatch event to ChatComponent
            window.Livewire.dispatch('edit-idea-in-chat', { idea: content });
            console.log('Event dispatched to ChatComponent');
        },
        
        editFavoriteInChatFromButton(button) {
            console.log('editFavoriteInChatFromButton called');
            const content = button.getAttribute('data-content');
            console.log('Raw content from data attribute:', content);
            if (content) {
                // Decode HTML entities
                const decodedContent = content.replace(/&quot;/g, '"');
                console.log('Decoded content:', decodedContent);
                this.editFavoriteInChat(decodedContent);
            } else {
                console.log('No content found in data attribute');
            }
        },
         
                  // Listen for tab changes
         onTabChange() {
             console.log('Tab changed to favorites, rendering...');
             this.loadFavorites(); // Reload from localStorage
             this.renderFavorites();
         }
     }
 }
 
 // Listen for Livewire events
document.addEventListener('livewire:init', () => {
    Livewire.on('render-favorites', () => {
        // Wait a bit for Alpine to be ready
        setTimeout(() => {
            if (window.dailyPostIdeasInstance) {
                window.dailyPostIdeasInstance.loadFavorites();
                window.dailyPostIdeasInstance.renderFavorites();
            }
        }, 200);
    });
});
 </script> 