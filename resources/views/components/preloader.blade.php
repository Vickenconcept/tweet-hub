<div 
    x-data="{ show: true, init() { setTimeout(() => this.show = false, 500); } }" 
    x-init="init()" 
    x-show="show"  
    x-transition.opacity.duration.400ms
    id="global-preloader"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/80 backdrop-blur-sm"
>
    <div class="flex flex-col items-center gap-3">
        <span class="inline-block animate-spin text-green-600 text-5xl">
            <i class='bx bx-loader-circle'></i>
        </span>
        <span class="text-lg font-semibold text-green-700">Loading...</span>
    </div>
</div>
