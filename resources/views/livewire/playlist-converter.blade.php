<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Playlist Converter</h1>

    @if($success)
        <div class="mb-4 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-300">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ $success }}
        </div>
    @endif

    @if($error)
        <div class="mb-4 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 border border-red-300">
            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ $error }}
        </div>
    @endif

    <form wire:submit="convert" class="space-y-6">
        <!-- Source Platform Selection -->
        <div>
            <label class="block text-sm font-medium mb-2">Source Platform</label>
            <select wire:model.live="sourcePlatform" class="w-full p-2 border rounded">
                <option value="spotify">Spotify</option>
                <option value="youtube">YouTube</option>
            </select>
        </div>

        <!-- Destination Platform Selection -->
        <div>
            <label class="block text-sm font-medium mb-2">Destination Platform</label>
            <select wire:model.live="destinationPlatform" class="w-full p-2 border rounded">
                <option value="youtube">YouTube</option>
                <option value="spotify">Spotify</option>
            </select>
        </div>

        <!-- Source Playlist URL -->
        <div>
            <label for="sourcePlaylistUrl" class="block text-sm font-medium mb-2">
                @if($sourcePlatform === 'spotify')
                    Spotify Playlist URL
                @else
                    YouTube Playlist URL
                @endif
            </label>
            <input 
                type="text" 
                id="sourcePlaylistUrl"
                wire:model="sourcePlaylistUrl" 
                placeholder="@if($sourcePlatform === 'spotify')https://open.spotify.com/playlist/...@elsehttps://www.youtube.com/playlist?list=...@endif"
                class="w-full p-2 border rounded"
                required
            >
            <p class="mt-1 text-sm text-gray-500">
                Paste your {{ ucfirst($sourcePlatform) }} playlist URL here
            </p>
        </div>

        <!-- Authentication Status -->
        @if($sourcePlatform === 'spotify' && $destinationPlatform === 'youtube')
            <!-- Spotify to YouTube: Need YouTube auth -->
            @if(!$isYouTubeAuthenticated)
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded">
                    <p class="mb-3 text-sm text-yellow-800">
                        You need to authenticate with YouTube to convert playlists.
                    </p>
                    <button 
                        type="button"
                        wire:click="authenticateWithYouTube"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    >
                        Authenticate with YouTube
                    </button>
                </div>
            @else
                <div class="p-4 bg-green-50 border border-green-200 rounded">
                    <p class="text-sm text-green-800">
                        ✓ Authenticated with YouTube
                    </p>
                </div>
            @endif

            <!-- YouTube Playlist URL (Optional) -->
            @if($isYouTubeAuthenticated)
                <div>
                    <label for="destinationPlaylistUrl" class="block text-sm font-medium mb-2">
                        YouTube Playlist URL (Optional)
                    </label>
                    <input 
                        type="text" 
                        id="destinationPlaylistUrl"
                        wire:model="destinationPlaylistUrl" 
                        placeholder="https://www.youtube.com/playlist?list=..."
                        class="w-full p-2 border rounded"
                    >
                    <p class="mt-1 text-sm text-gray-500">
                        Leave empty to create a new playlist, or paste an existing playlist URL to add tracks to it
                    </p>
                </div>
            @endif
        @elseif($sourcePlatform === 'youtube' && $destinationPlatform === 'spotify')
            <!-- YouTube to Spotify: Need both YouTube (to read) and Spotify (to write) -->
            <div class="space-y-3">
                @if(!$isYouTubeAuthenticated)
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="mb-3 text-sm text-yellow-800">
                            You need to authenticate with YouTube to read the playlist.
                        </p>
                        <button 
                            type="button"
                            wire:click="authenticateWithYouTube"
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                        >
                            Authenticate with YouTube
                        </button>
                    </div>
                @else
                    <div class="p-4 bg-green-50 border border-green-200 rounded">
                        <p class="text-sm text-green-800">
                            ✓ Authenticated with YouTube
                        </p>
                    </div>
                @endif

                @if(!$isSpotifyAuthenticated)
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="mb-3 text-sm text-yellow-800">
                            You need to authenticate with Spotify to create playlists.
                        </p>
                        <button 
                            type="button"
                            wire:click="authenticateWithSpotify"
                            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                        >
                            Authenticate with Spotify
                        </button>
                    </div>
                @else
                    <div class="p-4 bg-green-50 border border-green-200 rounded">
                        <p class="text-sm text-green-800">
                            ✓ Authenticated with Spotify
                        </p>
                    </div>
                @endif
            </div>

            <!-- Spotify Playlist URL (Optional) -->
            @if($isYouTubeAuthenticated && $isSpotifyAuthenticated)
                <div>
                    <label for="destinationPlaylistUrl" class="block text-sm font-medium mb-2">
                        Spotify Playlist URL (Optional)
                    </label>
                    <input 
                        type="text" 
                        id="destinationPlaylistUrl"
                        wire:model="destinationPlaylistUrl" 
                        placeholder="https://open.spotify.com/playlist/..."
                        class="w-full p-2 border rounded"
                    >
                    <p class="mt-1 text-sm text-gray-500">
                        Leave empty to create a new playlist, or paste an existing playlist URL to add tracks to it
                    </p>
                </div>
            @endif
        @endif

        <!-- Convert Button -->
        @if(($sourcePlatform === 'spotify' && $destinationPlatform === 'youtube' && $isYouTubeAuthenticated) ||
            ($sourcePlatform === 'youtube' && $destinationPlatform === 'spotify' && $isYouTubeAuthenticated && $isSpotifyAuthenticated))
            <button 
                type="submit"
                wire:loading.attr="disabled"
                class="w-full px-6 py-3 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span wire:loading.remove>Convert Playlist</span>
                <span wire:loading>Converting...</span>
            </button>
        @endif
    </form>

    <!-- Conversion Results -->
    @if($conversionResult)
        <div class="mt-8 p-6 bg-gray-50 rounded-lg">
            <h2 class="text-2xl font-bold mb-4">Conversion Results</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- Source Playlist -->
                <div class="border rounded-lg p-4 bg-white">
                    <h3 class="font-semibold mb-2">
                        @if($sourcePlatform === 'spotify')
                            Spotify Playlist
                        @else
                            YouTube Playlist
                        @endif
                    </h3>
                    @if($sourcePlatform === 'spotify')
                        <p class="text-gray-600">{{ $conversionResult['spotify_playlist']['name'] }}</p>
                        <p class="text-sm text-gray-500 mb-2">{{ count($conversionResult['spotify_playlist']['tracks']) }} tracks</p>
                        <a 
                            href="{{ $conversionResult['spotify_playlist']['id'] ? 'https://open.spotify.com/playlist/' . $conversionResult['spotify_playlist']['id'] : '#' }}" 
                            target="_blank"
                            class="text-green-600 hover:underline text-sm"
                        >
                            Open in Spotify →
                        </a>
                    @else
                        <p class="text-gray-600">{{ $conversionResult['youtube_playlist']['title'] }}</p>
                        <p class="text-sm text-gray-500 mb-2">{{ count($conversionResult['youtube_playlist']['videos'] ?? []) }} videos</p>
                        <a 
                            href="https://www.youtube.com/playlist?list={{ $conversionResult['youtube_playlist']['id'] }}" 
                            target="_blank"
                            class="text-red-600 hover:underline text-sm"
                        >
                            Open in YouTube →
                        </a>
                    @endif
                </div>

                <!-- Destination Playlist -->
                <div class="border rounded-lg p-4 bg-white">
                    <h3 class="font-semibold mb-2">
                        @if($destinationPlatform === 'spotify')
                            Spotify Playlist
                        @else
                            YouTube Playlist
                        @endif
                    </h3>
                    @if($destinationPlatform === 'spotify')
                        <p class="text-gray-600">{{ $conversionResult['spotify_playlist']['name'] }}</p>
                        <p class="text-sm text-gray-500 mb-2">{{ count($conversionResult['spotify_playlist']['tracks']) }} tracks</p>
                        <a 
                            href="https://open.spotify.com/playlist/{{ $conversionResult['spotify_playlist']['id'] }}" 
                            target="_blank"
                            class="text-green-600 hover:underline text-sm"
                        >
                            Open in Spotify →
                        </a>
                    @else
                        <p class="text-gray-600">{{ $conversionResult['youtube_playlist']['title'] }}</p>
                        <p class="text-sm text-gray-500 mb-2">{{ count($conversionResult['youtube_playlist']['videos'] ?? []) }} videos</p>
                        <a 
                            href="https://www.youtube.com/playlist?list={{ $conversionResult['youtube_playlist']['id'] }}" 
                            target="_blank"
                            class="text-red-600 hover:underline text-sm"
                        >
                            Open in YouTube →
                        </a>
                    @endif
                </div>
            </div>

            <div class="mt-6">
                <h3 class="font-semibold mb-2">Conversion Summary</h3>
                @php
                    $found = collect($conversionResult['conversion_results'])->where('found', true)->count();
                    $notFound = collect($conversionResult['conversion_results'])->where('found', false)->count();
                    $total = count($conversionResult['conversion_results']);
                @endphp
                
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span>Total items:</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </div>
                    <div class="flex justify-between text-green-600">
                        <span>Successfully converted:</span>
                        <span class="font-semibold">{{ $found }}</span>
                    </div>
                    @if($notFound > 0)
                        <div class="flex justify-between text-red-600">
                            <span>Not found:</span>
                            <span class="font-semibold">{{ $notFound }}</span>
                        </div>
                    @endif
                </div>

                @if($notFound > 0)
                    <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded">
                        <h4 class="font-semibold mb-2">Items Not Found:</h4>
                        <ul class="list-disc list-inside space-y-1 text-sm">
                            @foreach($conversionResult['conversion_results'] as $result)
                                @if(!$result['found'])
                                    @if($sourcePlatform === 'spotify')
                                        <li>{{ $result['spotify_track']['name'] ?? 'Unknown' }} - {{ implode(', ', $result['spotify_track']['artists'] ?? []) }}</li>
                                    @else
                                        <li>{{ $result['youtube_video']['title'] ?? 'Unknown' }}</li>
                                    @endif
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
