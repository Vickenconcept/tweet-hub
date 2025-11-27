<!DOCTYPE html>
<html lang="en" class="h-full bg-white ">

<head>
    <x-seo::meta />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @seo([
        'title' => 'xengager',
        'description' => 'xengager for X',
        'image' => asset('images/meta-image.png'),
        'site_name' => config('app.name'),
        'favicon' => asset('favicon.ico'),
    ])

    <title>xengager for X</title>

    <script src="https://unpkg.com/boxicons@2.1.4/dist/boxicons.js"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    {{-- <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>


    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    {{-- <script src="https://unpkg.com/@alpinejs/focus" defer></script> --}}

    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js"
        integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://upload-widget.cloudinary.com/latest/global/all.js" type="text/javascript"></script>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">



    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

 


    @yield('styles')

    @livewireStyles
</head>

<body class="h-screen font-['Poppins'] ">
    <div class="fixed w-full z-50">
        <button id="open-chat" onclick="toggleChatAndCloseSidebar(); return false;"
            class=" py-2 px-4 text-sm  lg:flex rounded-full cursor-pointer bg-green-500 text-white absolute top-5 right-3 z-40 flex items-center justify-center">
            <i class='bx  bx-sidebar mr-2 text-xl'  ></i>  open chat
        </button>
    </div>
    <x-preloader />
    <div id="app" class="h-full  text-gray-700 ">
        <x-notification />
        <livewire:connect-x-modal />
        <x-navbar />
        <x-sidebar />
        

        <div id="main-section" class="h-full sm:ml-64  pt-16 relative flex ">
        {{-- <div id="main-section" class="h-full sm:ml-64 bg-gray-100 pt-20 overflow-y-hidden relative flex "> --}}
            {{-- <div>
                <button id="toggle-btn"
                    class=" p-2 hidden lg:flex rounded-r-md cursor-pointer bg-black text-white absolute top-5 -left-3 z-40 flex items-center justify-center">
                    <i class='bx  bx-sidebar ml-2 text-xl'  ></i> 
                </button>
            </div> --}}
            {{-- <div class="">
                <button id="open-chat" onclick="toggleChatAndCloseSidebar(); return false;"
                    class=" py-2 px-4 text-sm  lg:flex rounded-full cursor-pointer bg-green-500 text-white absolute top-5 right-3 z-40 flex items-center justify-center">
                    <i class='bx  bx-sidebar mr-2 text-xl'  ></i>  open chat
                </button>
            </div> --}}
            <style>
                #open-chat .chat-text {
                    display: none;
                    transition: opacity 0.2s;
                    opacity: 0;
                }
                #open-chat:hover .chat-text {
                    display: inline;
                    opacity: 1;
                }
            </style>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const openChatBtn = document.getElementById('open-chat');
                    // Remove text from button, keep only icon and span for text
                    openChatBtn.innerHTML = `<i class='bx bx-sidebar mr-2 text-xl'></i> <span class="chat-text">open chat</span>`;
                    
                });
            </script>
            <div class="flex-grow">
            {{-- <div class="flex-grow w-full"> --}}
                {{ $slot }}
            </div>
            <aside id="chat-area" class="w-[50%]" >
            {{-- <aside id="chat-area" class=" bg-white  border rounded-3xl border-gray-300 w-[50%]" style="box-shadow: -5px 0px 5px rgb(231, 229, 229);"> --}}
                <div class="fixed top-16 right-0 z-50 h-screen border rounded-3xl border-gray-300  w-[100%] md:w-[30%] bg-white" style="box-shadow: -5px 0px 5px rgb(231, 229, 229);">
                    <livewire:chat-component />
                </div>
                </aside>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatArea = document.getElementById('chat-area');
            const openChatBtn = document.getElementById('open-chat');
            let chatOpen = false;

            // Initial styles for smooth transition
            chatArea.style.transition = 'all 0.3s ease';
            chatArea.style.width = '0';
            chatArea.style.opacity = '0';
            chatArea.style.overflow = 'hidden';
            chatArea.style.display = 'none';

            // Unified function to toggle chat and manage sidebar
            window.toggleChatAndCloseSidebar = function() {
                // Toggle chat state
                chatOpen = !chatOpen;
                
                const logoSidebar = document.getElementById('logo-sidebar');
                const mainSection = document.getElementById('main-section');
                
                if (chatOpen) {
                    // Open chat - close sidebar
                    chatArea.style.display = 'block';
                    setTimeout(() => {
                        chatArea.style.width = '35%';
                        chatArea.style.opacity = '1';
                    }, 10);
                    if (openChatBtn) {
                        openChatBtn.innerHTML = `<i class='bx bx-sidebar mr-2 text-xl'></i> <span class="chat-text">close chat</span>`;
                    }
                    
                    // Close sidebar when opening chat
                    if (logoSidebar && mainSection) {
                        if (!logoSidebar.classList.contains('hidden')) {
                            logoSidebar.classList.add('hidden');
                            mainSection.classList.remove('sm:ml-64');
                        }
                    }
                } else {
                    // Close chat - open sidebar
                    chatArea.style.width = '0';
                    chatArea.style.opacity = '0';
                    if (openChatBtn) {
                        openChatBtn.innerHTML = `<i class='bx bx-sidebar mr-2 text-xl'></i> <span class="chat-text">open chat</span>`;
                    }
                    setTimeout(() => {
                        chatArea.style.display = 'none';
                    }, 300);
                    
                    // Open sidebar when closing chat
                    if (logoSidebar && mainSection) {
                        if (logoSidebar.classList.contains('hidden')) {
                            logoSidebar.classList.remove('hidden');
                            mainSection.classList.add('sm:ml-64');
                        }
                    }
                }
                
                // Update chat menu item state
                updateChatMenuItemState();
            };
            
            // Function to update chat menu item active state
            // Note: Chat menu item always stays active (default active state)
            window.updateChatMenuItemState = function() {
                const chatMenuItem = document.getElementById('chat-menu-item');
                const chatMenuIcon = document.getElementById('chat-menu-icon');
                
                if (chatMenuItem && chatMenuIcon) {
                    // Always keep active state - chat button is always selected
                    chatMenuItem.classList.remove('text-gray-700', 'hover:bg-gray-50');
                    chatMenuItem.classList.add('bg-green-50', 'text-green-700', 'font-semibold', 'shadow-sm');
                    chatMenuIcon.classList.remove('bg-gray-100', 'text-gray-500', 'group-hover:bg-gray-200');
                    chatMenuIcon.classList.add('bg-green-100', 'text-green-600');
                }
            };
            
            // Initial state update - ensure active state is applied
            setTimeout(() => {
                window.updateChatMenuItemState();
            }, 100);
            
            // Observe chat area for external changes
            const observer = new MutationObserver(function() {
                // Check actual state from DOM
                const currentWidth = chatArea.style.width || window.getComputedStyle(chatArea).width;
                const currentDisplay = chatArea.style.display || window.getComputedStyle(chatArea).display;
                const isActuallyOpen = currentWidth && currentWidth !== '0px' && currentWidth !== '0' && currentDisplay !== 'none';
                
                if (isActuallyOpen !== chatOpen) {
                    chatOpen = isActuallyOpen;
                    window.updateChatMenuItemState();
                }
            });
            
            observer.observe(chatArea, {
                attributes: true,
                attributeFilter: ['style']
            });
        });
    </script>
    @yield('scripts')
    @stack('scripts')

    @livewireScripts

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('tweet-posted', (data) => {
                Toastify({
                    text: data.message,
                    duration: 4000,
                    gravity: 'top',
                    position: 'right',
                    backgroundColor: '#2563eb',
                    stopOnFocus: true,
                }).showToast();
            });

            // Listen for open-chat event to automatically open chat
            Livewire.on('open-chat', () => {
                const chatArea = document.getElementById('chat-area');
                const openChatBtn = document.getElementById('open-chat');
                
                if (chatArea && openChatBtn) {
                    // Check if chat is already open
                    const currentWidth = chatArea.style.width;
                    if (currentWidth === '0px' || currentWidth === '0' || !currentWidth) {
                        // Chat is closed, open it
                        chatArea.style.display = 'block';
                        setTimeout(() => {
                            chatArea.style.width = '35%';
                            chatArea.style.opacity = '1';
                        }, 10);
                        openChatBtn.innerHTML = `<i class='bx bx-sidebar mr-2 text-xl'></i> close chat`;
                        
                        // Update the global chatOpen state if it exists
                        if (window.chatOpen !== undefined) {
                            window.chatOpen = true;
                        }
                    }
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Livewire.on('refreshPage', () => {
                location.reload();
            });
        });
    </script>

    <script>
        function toggleSidebar() {
            const logoSidebar = document.getElementById('logo-sidebar');
            const mainSection = document.getElementById('main-section');

            logoSidebar.classList.toggle('hidden');
            mainSection.classList.toggle('sm:ml-64');
        }
        // Example usage on a button click
        document.getElementById('toggle-btn').onclick = toggleSidebar;
    </script>

</body>

</html>
