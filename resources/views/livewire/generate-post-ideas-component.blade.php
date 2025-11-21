<div class="space-y-6" x-data="generatePostIdeas()">
    <!-- Header Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <p class="text-sm uppercase tracking-[0.4em] text-green-500">AI Post Generator</p>
                <h1 class="text-3xl md:text-4xl font-semibold text-gray-900 mt-2">
                    Generate Tweet Ideas
                </h1>
                @if($hasCachedIdeas && count($generatedIdeas) > 0)
                    <p class="text-gray-500 mt-2 text-sm md:text-base">
                        <i class="bx bx-check-circle mr-1 text-green-600"></i>
                        {{ count($generatedIdeas) }} cached ideas available
                    </p>
                @endif
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                @if($hasCachedIdeas && count($generatedIdeas) > 0)
                    <button wire:click="clearIdeas" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl border border-red-200 text-sm font-semibold text-red-600 hover:border-red-300">
                        <i class='bx bx-trash text-lg'></i> Clear Cache
                    </button>
                @endif
                <button wire:click="clearIdeas" class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-gray-100 text-gray-700 text-sm font-semibold hover:bg-gray-200">
                    <i class='bx bx-trash text-lg'></i> Clear All
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-2">
        <div class="flex space-x-1">
            <button wire:click="setTab('generate')" 
                    class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'generate' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
                Generate Ideas
            </button>
            <button wire:click="setTab('favorites')" 
                    x-on:click="onTabChange()"
                    class="flex-1 px-4 py-3 text-sm font-medium rounded-2xl transition-all duration-200 cursor-pointer {{ $activeTab === 'favorites' ? 'bg-green-50 text-green-700 font-semibold shadow-sm' : 'text-gray-600 hover:bg-gray-50' }}">
                Favorites
            </button>
        </div>
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
        <div class="bg-white rounded-3xl shadow-sm border border-red-200 p-4">
            <div class="flex items-center text-red-700">
                <i class="bx bx-error-circle mr-2"></i>
                <span>{{ $errorMessage }}</span>
            </div>
        </div>
    @endif

    <!-- Input Form -->
    @if($activeTab === 'generate')
        @if($hasCachedIdeas && count($generatedIdeas) > 0)
            <div class="bg-white rounded-3xl shadow-sm border border-blue-200 p-4">
                <div class="flex items-center text-blue-700">
                    <i class="bx bx-info-circle text-xl mr-2"></i>
                    <span class="text-sm">You have {{ count($generatedIdeas) }} cached ideas. Generate new ones below or view them in the results section.</span>
                </div>
            </div>
        @endif
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
            <div class="mb-6">
                <p class="text-sm uppercase tracking-[0.3em] text-gray-400">Generator Settings</p>
                <h3 class="text-2xl font-semibold text-gray-900 mt-2">Create Custom Tweet Ideas</h3>
            </div>
            
            <div class="mb-6">
                <label for="prompt" class="block text-sm font-semibold text-gray-800 mb-2">Describe what you want to post about</label>
                <textarea wire:model="prompt" id="prompt" rows="4"
                          class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm resize-none"
                          placeholder="e.g., I want to share tips about productivity for remote workers..."></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="ideaType" class="block text-sm font-semibold text-gray-800 mb-2">Content Type</label>
                    <select wire:model="ideaType" id="ideaType" 
                            class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        <option value="general">General</option>
                        <option value="educational">Educational</option>
                        <option value="entertaining">Entertaining</option>
                        <option value="inspirational">Inspirational</option>
                        <option value="promotional">Promotional</option>
                    </select>
                </div>
                <div>
                    <label for="tone" class="block text-sm font-semibold text-gray-800 mb-2">Tone</label>
                    <select wire:model="tone" id="tone" 
                            class="w-full px-4 py-3 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        <option value="professional">Professional</option>
                        <option value="casual">Casual</option>
                        <option value="humorous">Humorous</option>
                        <option value="formal">Formal</option>
                    </select>
                </div>
            </div>

            <button wire:click="generatePostIdeas" 
                    wire:loading.attr="disabled"
                    class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-2xl hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-lg shadow-green-200">
                <i class="bx bx-refresh text-lg"></i>
                <span wire:loading.remove wire:target="generatePostIdeas">Generate Post Ideas</span>
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
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="w-16 h-16 bg-green-50 rounded-3xl flex items-center justify-center mx-auto mb-6">
                <i class="bx bx-edit text-3xl text-green-600"></i>
            </div>
            <h3 class="text-2xl font-semibold text-gray-900 mb-3">Ready to Generate Ideas!</h3>
            <p class="text-gray-500 mb-6">Describe what you want to post about to generate creative ideas</p>
            <p class="text-xs text-gray-400">Ideas will appear here once generated</p>
        </div>
    @endif

    <!-- Favorites Tab -->
    @if($activeTab === 'favorites')
        <div class="space-y-6">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-gray-400">Saved Ideas</p>
                        <h3 class="text-2xl font-semibold text-gray-900 mt-2">Favorite Ideas</h3>
                    </div>
                    <button x-on:click="loadFavorites(); renderFavorites();" 
                            class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 border border-gray-200 rounded-2xl hover:border-gray-300 transition-colors">
                        <i class="bx bx-refresh text-lg"></i> Refresh
                    </button>
                </div>
            </div>
            <div id="favorites-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Favorites will be loaded here via JavaScript -->
            </div>
            <div id="no-favorites" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center" style="display: none;">
                <div class="w-16 h-16 bg-amber-50 rounded-3xl flex items-center justify-center mx-auto mb-6">
                    <i class="bx bx-star text-3xl text-amber-500"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No favorite ideas yet</h3>
                <p class="text-gray-500 text-sm">Star ideas from the Generate Ideas tab to save them here</p>
            </div>
        </div>
    @endif

    <!-- Loading State -->
    @if($loading)
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-4 border-green-200 border-t-green-600 mb-6"></div>
            <h3 class="text-2xl font-semibold text-gray-900 mb-3">Generating Post Ideas...</h3>
            <p class="text-gray-500">Creating creative content based on your input</p>
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
                <div class="bg-white rounded-3xl border border-gray-100 p-6 shadow-sm hover:shadow-md transition-all duration-200">
                    <div class="flex items-start justify-between mb-4">
                        <span class="inline-flex items-center justify-center w-8 h-8 bg-amber-50 text-amber-600 text-sm font-semibold rounded-2xl">
                            ${index + 1}
                        </span>
                        <button onclick="window.generatePostIdeasInstance.removeFavorite('${favorite.id}')" 
                                class="text-red-500 hover:text-red-600 transition-colors cursor-pointer">
                            <i class="bx bx-trash text-xl"></i>
                        </button>
                    </div>
                    <p class="text-gray-700 mb-4 text-sm leading-relaxed" style="display: -webkit-box; -webkit-line-clamp: 8; -webkit-box-orient: vertical; overflow: hidden;">${favorite.content}</p>
                    <button onclick="window.generatePostIdeasInstance.editFavoriteInChatFromButton(this)" 
                            data-content="${favorite.content.replace(/"/g, '&quot;')}"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-green-600 bg-green-50 rounded-2xl hover:bg-green-100 transition-colors">
                        <i class="bx bx-edit text-lg"></i>
                        Edit in Chat
                    </button>
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