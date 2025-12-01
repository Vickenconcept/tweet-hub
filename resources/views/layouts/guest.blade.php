<!DOCTYPE html>
<html lang="en" class="h-full bg-white">

<head>
    <x-seo::meta />
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @seo([  
        'title' => 'xengager for X',
        'description' => 'xengager for X',
        'image' => asset('images/meta-image.png'),
        'site_name' => config('app.name'),
        'favicon' => asset('favicon.ico'),
    ])

    <title>xengager for X</title>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>


    @vite(['resources/css/app.css', 'resources/js/app.js'])
    



    @yield('styles')

    @livewireStyles
</head>

<body class="h-full !font-['Poppins']">
    <marquee direction="right" scrollamount="60" class="z-50 fixed w-full hidden" id="hiddenLinearPreloader">
        <div class="bg-gradient-to-r from-gray-800 from-70%  to-green-500 w-[700px] p-1 rounded-full"></div>
    </marquee>
    {{ $slot }}

</body>



<script>
    window.addEventListener('beforeunload', function(event) {
        var hiddenText = document.getElementById('hiddenText');
        hiddenText.classList.remove('hidden');
        hiddenLinearPreloader.classList.remove("hidden");
    });

    document.addEventListener("DOMContentLoaded", function() {
            var hiddenLinearPreloader = document.getElementById("hiddenLinearPreloader");

            hiddenLinearPreloader.classList.remove("hidden");

            setTimeout(function() {
                hiddenLinearPreloader.classList.add("hidden");
            }, 2000);
        });
</script>


@yield('scripts')
@livewireScripts

</html>
