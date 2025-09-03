<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
<div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">My Assets</h1>
                <p class="text-lg text-gray-600">Manage your uploaded images and GIFs</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-xl">
                    {{ count($assets) }} {{ count($assets) === 1 ? 'asset' : 'assets' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Messages -->
    @if($successMessage)
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-xl">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ $successMessage }}
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                {{ $errorMessage }}
            </div>
        </div>
    @endif

    <!-- Upload Section -->
    <div class="mb-8">
        <div class="bg-white rounded-2xl shadow-2xl shadow-gray-200 p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2 text-blue-600">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                Upload New Asset
            </h2>
            
            <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 transition-colors">
                <input type="file" 
                       class="hidden" 
                       wire:model.live="assetUpload" 
                       accept="image/*" 
                       id="asset-upload">
                
                <label for="asset-upload" class="cursor-pointer">
                    <div wire:loading.remove wire:target="assetUpload">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-12 mx-auto text-gray-400 mb-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                        <p class="text-lg font-medium text-gray-900 mb-2">Click to upload image or GIF</p>
                        <p class="text-sm text-gray-500">Images & GIFs: 5MB max (Videos temporarily disabled)</p>
                    </div>
                    
                    <div wire:loading wire:target="assetUpload" class="flex flex-col items-center">
                        <svg class="animate-spin size-12 text-blue-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-lg font-medium text-blue-600">Uploading...</p>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <!-- Assets Grid -->
    @if(count($assets) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            @foreach($assets as $asset)
                <div class="bg-white rounded-2xl shadow-2xl shadow-gray-200 hover:shadow-blue-100 transition-all duration-300 overflow-hidden group">
                    <!-- Media Preview -->
                    <div class="aspect-square bg-gray-100 relative overflow-hidden">
                        @if($asset->type === 'video')
                            <video src="{{ $asset->path }}" 
                                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                   muted
                                   onerror="console.log('Video failed to load:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            </video>
                        @else
                            <img src="{{ $asset->path }}" 
                                 alt="{{ $asset->original_name }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                 onerror="console.log('Media failed to load:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        @endif
                        
                        <!-- Media Type Badge -->
                        <div class="absolute top-2 left-2">
                            @if($asset->type === 'video')
                                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-lg flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    VIDEO
                                </span>
                            @elseif($asset->type === 'image' && str_contains($asset->original_name, '.gif'))
                                <span class="bg-purple-500 text-white text-xs px-2 py-1 rounded-lg flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                    </svg>
                                    GIF
                                </span>
                            @else
                                <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-lg flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3 mr-1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    IMAGE
                                </span>
                            @endif
                        </div>
                        
                        <!-- Fallback for failed media -->
                        <div class="w-full h-full flex items-center justify-center bg-gray-200" style="display: none;">
                            <div class="text-center text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-12 mx-auto mb-2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <p class="text-sm">Media not found</p>
                                <p class="text-xs text-gray-400">{{ $asset->path }}</p>
                            </div>
                        </div>
                        
                        <!-- Overlay Actions -->
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex gap-2">
                                <button wire:click="copyAssetCode('{{ $asset->code }}')"
                                        class="p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors"
                                        title="Copy code">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                    </svg>
                                </button>
                                <button wire:click="deleteAsset({{ $asset->id }})"
                                        onclick="return confirm('Are you sure you want to delete this asset?')"
                                        class="p-2 bg-red-600 text-white rounded-xl hover:bg-red-700 transition-colors"
                                        title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Asset Info -->
                    <div class="p-4">
                        <h3 class="font-medium text-gray-900 truncate mb-1" title="{{ $asset->original_name }}">
                            {{ $asset->original_name }}
                        </h3>
                        <p class="text-sm text-gray-500 mb-2">
                            {{ $asset->created_at->format('M j, Y g:i A') }}
                        </p>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-400 font-mono bg-gray-100 px-2 py-1 rounded-lg">
                                {{ $asset->code }}
                            </span>
                            <span class="text-xs text-blue-600 bg-blue-100 px-2 py-1 rounded-lg">
                                {{ strtoupper($asset->type) }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-12">
            <div class="bg-white rounded-2xl shadow-2xl shadow-gray-200 p-12">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 mx-auto text-gray-400 mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No assets yet</h3>
                <p class="text-gray-600 mb-6">Upload your first image or GIF to get started!</p>
                <label for="asset-upload" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-400 to-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition-colors cursor-pointer shadow-2xl shadow-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Upload Image/GIF
                </label>
            </div>
        </div>
    @endif
</div>

<!-- Copy to Clipboard Script -->
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('copy-to-clipboard', (event) => {
            const code = event.code;
            navigator.clipboard.writeText(code).then(() => {
                // Show a temporary success message
                const notification = document.createElement('div');
                notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                notification.textContent = 'Code copied to clipboard!';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        });
    });
</script>