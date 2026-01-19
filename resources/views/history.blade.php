<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    @livewireStyles
    <title>Conversion History - Playlist Converter</title>
</head>
<body class="min-h-screen bg-gray-50">
    <nav class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="{{ route('home') }}" class="text-xl font-semibold text-gray-900 hover:text-gray-700">
                        Playlist Converter
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('home') }}" class="text-sm text-gray-600 hover:text-gray-900">Converter</a>
                    <a href="{{ route('history') }}" class="text-sm font-medium text-blue-600">History</a>
                    <span class="text-sm text-gray-600">{{ Auth::user()->name ?? Auth::user()->email }}</span>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button 
                            type="submit" 
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mx-auto py-8">
        @livewire('conversion-history')
    </div>
    @livewireScripts
</body>
</html>
