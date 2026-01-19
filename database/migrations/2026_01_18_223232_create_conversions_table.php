<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('spotify_playlist_id');
            $table->string('spotify_playlist_name');
            $table->text('spotify_playlist_url');
            $table->string('youtube_playlist_id');
            $table->string('youtube_playlist_title');
            $table->text('youtube_playlist_url');
            $table->integer('total_tracks')->default(0);
            $table->integer('converted_tracks')->default(0);
            $table->integer('failed_tracks')->default(0);
            $table->json('conversion_results')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};
