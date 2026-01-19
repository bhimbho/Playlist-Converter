<div class="max-w-6xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Conversion History</h1>
        <a href="{{ route('home') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            ← Back to Converter
        </a>
    </div>

    @if($conversions->count() > 0)
        <div class="space-y-4">
            @foreach($conversions as $conversion)
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $conversion->spotify_playlist_name }}</h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Converted {{ $conversion->created_at->format('M d, Y g:i A') }}
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                @if($conversion->failed_tracks == 0) bg-green-100 text-green-800
                                @elseif($conversion->converted_tracks > 0) bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800
                                @endif">
                                {{ $conversion->converted_tracks }}/{{ $conversion->total_tracks }} tracks
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Spotify Playlist -->
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                </svg>
                                <h4 class="font-semibold text-gray-900">Spotify Playlist</h4>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">{{ $conversion->spotify_playlist_name }}</p>
                            <a 
                                href="{{ $conversion->spotify_playlist_url }}" 
                                target="_blank"
                                class="text-sm text-green-600 hover:underline inline-flex items-center"
                            >
                                Open in Spotify →
                            </a>
                        </div>

                        <!-- YouTube Playlist -->
                        <div class="border rounded-lg p-4 bg-gray-50">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"/>
                                </svg>
                                <h4 class="font-semibold text-gray-900">YouTube Playlist</h4>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">{{ $conversion->youtube_playlist_title }}</p>
                            <a 
                                href="{{ $conversion->youtube_playlist_url }}" 
                                target="_blank"
                                class="text-sm text-red-600 hover:underline inline-flex items-center"
                            >
                                Open in YouTube →
                            </a>
                        </div>
                    </div>

                    @if($conversion->failed_tracks > 0)
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                            <p class="text-sm text-yellow-800">
                                <strong>{{ $conversion->failed_tracks }}</strong> track(s) could not be found on YouTube
                            </p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $conversions->links() }}
        </div>
    @else
        <div class="text-center py-12 bg-white rounded-lg shadow border border-gray-200">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No conversions yet</h3>
            <p class="text-gray-500 mb-4">Start converting playlists to see them here</p>
            <a href="{{ route('home') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Convert a Playlist
            </a>
        </div>
    @endif
</div>
