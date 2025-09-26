<div class="" x-data="generatePostIdeas()">
    <div class="flex items-center justify-between mb-6">
        <div>
        <h2 class="text-2xl font-bold text-gray-900">Generate Post Ideas</h2>
            @if($hasCachedIdeas && count($generatedIdeas) > 0)
                <p class="text-sm text-green-600 mt-1">
                    <i class="bx bx-check-circle mr-1"></i>
                    {{ count($generatedIdeas) }} cached ideas available
                </p>
            @endif
        </div>
                <div class="flex gap-2">
            @if($hasCachedIdeas && count($generatedIdeas) > 0)
                <button wire:click="clearIdeas" class="px-4 py-2 text-sm font-medium text-red-600 bg-gradient-to-r from-red-100 to-red-200 rounded-xl hover:bg-red-200 transition-colors cursor-pointer">
                    <i class="bx bx-trash mr-1"></i>
                    Clear Cache
                </button>
            @endif
            <button wire:click="clearIdeas" class="px-4 py-2 text-sm font-medium text-gray-600 bg-gradient-to-r from-gray-100 to-gray-200 rounded-xl hover:bg-gray-200 transition-colors cursor-pointer">
            Clear All
        </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex space-x-1 mb-6 bg-gray-100 p-1 rounded-xl">
        <button wire:click="setTab('generate')" 
                class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer {{ $activeTab === 'generate' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">
            Generate Ideas
        </button>
        <button wire:click="setTab('favorites')" 
                x-on:click="onTabChange()"
                class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors cursor-pointer {{ $activeTab === 'favorites' ? 'bg-white text-blue-600 shadow-2xl shadow-gray-200' : 'text-gray-600 hover:text-gray-900' }}">
            Favorites
        </button>
    </div>

    <!-- Success/Error Messages -->
    {{-- @if($successMessage)
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl">
            <div class="flex items-center">
                <i class="bx bx-check-circle text-xl mr-2"></i>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif --}}

    @if($errorMessage)
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl">
            {{ $errorMessage }}
        </div>
    @endif

    <!-- Input Form -->
    @if($activeTab === 'generate')
        @if($hasCachedIdeas && count($generatedIdeas) > 0)
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 text-blue-700 rounded-xl">
                <div class="flex items-center">
                    <i class="bx bx-info-circle text-xl mr-2"></i>
                    <span>You have {{ count($generatedIdeas) }} cached ideas. Generate new ones below or view them in the results section.</span>
                </div>
            </div>
        @endif
        <div class="mb-6 p-6 bg-white rounded-xl shadow-2xl shadow-gray-200">
            <div class="mb-4">
                <label for="prompt" class="block text-sm font-medium text-gray-700 mb-2">Describe what you want to post about</label>
                <textarea wire:model="prompt" id="prompt" rows="3"
                          class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg"
                          placeholder="e.g., I want to share tips about productivity for remote workers..."></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label for="ideaType" class="block text-sm font-medium text-gray-700 mb-2">Content Type</label>
                    <select wire:model="ideaType" id="ideaType" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg">
                        <option value="general">General</option>
                        <option value="educational">Educational</option>
                        <option value="entertaining">Entertaining</option>
                        <option value="inspirational">Inspirational</option>
                        <option value="promotional">Promotional</option>
                    </select>
                </div>
                <div>
                    <label for="tone" class="block text-sm font-medium text-gray-700 mb-2">Tone</label>
                    <select wire:model="tone" id="tone" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg">
                        <option value="professional">Professional</option>
                        <option value="casual">Casual</option>
                        <option value="humorous">Humorous</option>
                        <option value="formal">Formal</option>
                    </select>
                </div>
                {{-- <div>
                    <label for="platform" class="block text-sm font-medium text-gray-700 mb-2">Platform</label>
                    <select wire:model="platform" id="platform" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg">
                        <option value="twitter">Twitter/X</option>
                        <option value="instagram">Instagram</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="facebook">Facebook</option>
                    </select>
                </div> --}}
            </div>

            <button wire:click="generatePostIdeas" 
                    wire:loading.attr="disabled"
                    class="w-full px-6 py-3 bg-gradient-to-r from-blue-400 to-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer shadow-2xl shadow-gray-200 flex items-center justify-center">
                <i class="bx bx-refresh mr-2"></i>
                <span wire:loading.remove  wire:target="generatePostIdeas">Generate Post Ideas</span>
                <span wire:loading wire:target="generatePostIdeas">Generating...</span>
                
            </button>
        </div>
    @endif

    <!-- Generated Ideas -->
    @if($activeTab === 'generate' && count($generatedIdeas) > 0)
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900">Generated Ideas ({{ count($generatedIdeas) }})</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($generatedIdeas as $index => $idea)
                    @php
                        $ideaId = md5($idea);
                    @endphp
                    <div class="p-4 rounded-2xl bg-white hover:bg-gray-50 transition-colors shadow-2xl shadow-gray-200">
                        <div class="flex items-start justify-between mb-3">
                            <span class="inline-block w-6 h-6 bg-blue-100 text-blue-600 text-sm font-medium rounded-full text-center">
                                {{ $index + 1 }}
                            </span>
                            <button class="text-yellow-500 hover:text-yellow-600 transition-colors cursor-pointer"
                                     x-on:click="toggleFavoriteFromButton()"
                                     data-idea-id="{{ $ideaId }}"
                                     data-idea-content="{{ htmlspecialchars($idea, ENT_QUOTES, 'UTF-8') }}">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     x-bind:fill="isFavorite('{{ $ideaId }}') ? 'currentColor' : 'none'"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                </svg>
                            </button>
                        </div>
                        <p class="text-gray-800 mb-4" style="display: -webkit-box; -webkit-line-clamp: 8; -webkit-box-orient: vertical; overflow: hidden;">{{ $idea }}</p>
                        <div class="flex gap-2">
                            <button wire:click="editInChat({{ $index }})" 
                                    class="w-full px-3 py-2 text-sm font-medium text-green-600 bg-gradient-to-r from-green-100 to-green-200 rounded-xl hover:bg-green-200 transition-colors flex items-center cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                                <span class="ml-2">Edit in Chat</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif($activeTab === 'generate' && !$loading)
        <div class="text-center py-12 text-gray-500">
            <div class="bg-gray-50 rounded-xl p-8 border border-gray-200">
                <i class="bx bx-edit text-6xl mb-6 text-blue-500"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-4">Ready to Generate Ideas!</h3>
                <p class="text-gray-600 mb-4">Describe what you want to post about to generate creative ideas</p>
                <p class="text-xs text-gray-500 mt-4">Ideas will appear here once generated</p>
            </div>
        </div>
    @endif

    <!-- Favorites Tab -->
    @if($activeTab === 'favorites')
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Favorite Ideas</h3>
                <button x-on:click="loadFavorites(); renderFavorites();" 
                        class="px-3 py-1 text-sm text-blue-600 bg-blue-100 rounded-xl hover:bg-blue-200 transition-colors cursor-pointer">
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
            <p class="mt-2 text-gray-600">Generating post ideas...</p>
        </div>
    @endif
</div>

<script>
function generatePostIdeas() {
    return {
        favorites: [],
        activeTab: '{{ $activeTab }}',
        
        init() {
            this.loadFavorites();
            this.renderFavorites();
            // Make instance globally available
            window.generatePostIdeasInstance = this;
            console.log('GeneratePostIdeas initialized, favorites:', this.favorites);
            
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
            const stored = localStorage.getItem('generatePostIdeas_favorites');
            this.favorites = stored ? JSON.parse(stored) : [];
            console.log('Loaded from localStorage:', this.favorites);
        },
        
        saveFavorites() {
            localStorage.setItem('generatePostIdeas_favorites', JSON.stringify(this.favorites));
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
                <div class="p-4 rounded-2xl bg-white hover:bg-gray-50 transition-colors shadow-2xl shadow-gray-200">
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-block w-6 h-6 bg-yellow-100 text-yellow-600 text-sm font-medium rounded-full text-center">
                            ${index + 1}
                        </span>
                        <button onclick="window.generatePostIdeasInstance.removeFavorite('${favorite.id}')" 
                                class="text-red-500 hover:text-red-600 transition-colors cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-gray-800 mb-4" style="display: -webkit-box; -webkit-line-clamp: 8; -webkit-box-orient: vertical; overflow: hidden;">${favorite.content}</p>
                    <div class="flex gap-2">
                        <button onclick="window.generatePostIdeasInstance.editFavoriteInChatFromButton(this)" 
                                data-content="${favorite.content.replace(/"/g, '&quot;')}"
                                class="w-full px-3 py-2 text-sm font-medium text-green-600 bg-gradient-to-r from-green-100 to-green-200 rounded-xl hover:bg-green-200 transition-colors flex items-center cursor-pointer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            <span class="ml-2">Edit in Chat</span>
                        </button>
                    </div>
                </div>
            `).join('');
        },
        
                 editFavoriteInChat(content) {
             console.log('editFavoriteInChat called with:', content);
             // Dispatch event to ChatComponent
             window.Livewire.dispatch('edit-idea-in-chat', { idea: content });
             // Also dispatch event to open chat
             window.Livewire.dispatch('open-chat');
             console.log('Event dispatched to ChatComponent and open-chat triggered');
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
            if (window.generatePostIdeasInstance) {
                window.generatePostIdeasInstance.loadFavorites();
                window.generatePostIdeasInstance.renderFavorites();
            }
        }, 200);
    });
});
</script> 