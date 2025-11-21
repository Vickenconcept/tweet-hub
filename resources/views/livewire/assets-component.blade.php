<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <p class="text-sm uppercase tracking-[0.4em] text-green-500">My Assets</p>
                <h1 class="text-3xl md:text-4xl font-semibold text-gray-900 mt-2">
                    Asset Library
                </h1>
                <p class="text-gray-500 mt-2 text-sm md:text-base">
                    <i class="bx bx-image mr-1 text-green-600"></i>
                    Manage your uploaded images and GIFs
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                <span class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-gray-50 border border-gray-200 text-sm font-semibold text-gray-700">
                    <i class="bx bx-folder text-lg"></i>
                    {{ count($assets) }} {{ count($assets) === 1 ? 'asset' : 'assets' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Messages -->
    @if($successMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-green-200 p-4">
            <div class="flex items-center text-green-700">
                <i class="bx bx-check-circle mr-2 text-lg"></i>
                <span>{{ $successMessage }}</span>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="bg-white rounded-3xl shadow-sm border border-red-200 p-4">
            <div class="flex items-center text-red-700">
                <i class="bx bx-error-circle mr-2 text-lg"></i>
                <span>{{ $errorMessage }}</span>
            </div>
        </div>
    @endif

    <!-- Upload Section -->
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 md:p-8">
        <div class="mb-6">
            <p class="text-sm uppercase tracking-[0.2em] text-gray-400">Upload</p>
            <h2 class="text-2xl font-semibold text-gray-900 mt-2 flex items-center">
                <div class="w-10 h-10 bg-green-50 rounded-2xl flex items-center justify-center mr-3">
                    <i class="bx bx-upload text-xl text-green-600"></i>
                </div>
                Upload New Asset
            </h2>
        </div>
        
        <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center hover:border-green-400 transition-colors">
            <input type="file" 
                   class="hidden" 
                   wire:model.live="assetUpload" 
                   accept="image/*" 
                   id="asset-upload">
            
            <label for="asset-upload" class="cursor-pointer">
                <div wire:loading.remove wire:target="assetUpload">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                        <i class="bx bx-image text-3xl text-gray-400"></i>
                    </div>
                    <p class="text-lg font-semibold text-gray-900 mb-2">Click to upload image or GIF</p>
                    <p class="text-sm text-gray-500">Images & GIFs: 5MB max (Videos temporarily disabled)</p>
                </div>
                
                <div wire:loading wire:target="assetUpload" class="flex flex-col items-center">
                    <div class="inline-block animate-spin rounded-full h-16 w-16 border-b-4 border-green-600 mb-4"></div>
                    <p class="text-lg font-semibold text-green-600">Uploading...</p>
                </div>
            </label>
        </div>
    </div>

    <!-- Assets Grid -->
    @if(count($assets) > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @foreach($assets as $asset)
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition-all duration-200 overflow-hidden group">
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
                                <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-lg flex items-center font-semibold">
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
                        <div class="absolute inset-0 bg-black/10 group-hover:bg-black/50 transition-all duration-300 flex items-center justify-center">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex gap-2">
                                <button wire:click="copyAssetCode('{{ $asset->code }}')"
                                        class="p-2.5 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-colors shadow-sm"
                                        title="Copy code">
                                    <i class="bx bx-copy text-lg"></i>
                                </button>
                                <button wire:click="deleteAsset({{ $asset->id }})"
                                        onclick="return confirm('Are you sure you want to delete this asset?')"
                                        class="p-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 transition-colors shadow-sm"
                                        title="Delete">
                                    <i class="bx bx-trash text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Asset Info -->
                    <div class="p-4">
                        <h3 class="font-semibold text-gray-900 truncate mb-1 text-sm" title="{{ $asset->original_name }}">
                            {{ $asset->original_name }}
                        </h3>
                        <p class="text-xs text-gray-500 mb-3">
                            <i class="bx bx-time mr-1"></i>
                            {{ $asset->created_at->format('M j, Y g:i A') }}
                        </p>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-500 font-mono bg-gray-50 border border-gray-200 px-2 py-1 rounded-lg truncate flex-1">
                                {{ $asset->code }}
                            </span>
                            <span class="text-xs text-green-600 bg-green-50 border border-green-200 px-2 py-1 rounded-lg font-semibold">
                                {{ strtoupper($asset->type) }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 rounded-full mb-6">
                <i class="bx bx-image text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No assets yet</h3>
            <p class="text-gray-600 mb-6">Upload your first image or GIF to get started!</p>
            <label for="asset-upload" class="inline-flex items-center gap-2 px-6 py-3 bg-green-600 text-white font-semibold rounded-2xl hover:bg-green-700 transition-colors cursor-pointer shadow-sm">
                <i class="bx bx-upload text-lg"></i>
                Upload Image/GIF
            </label>
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