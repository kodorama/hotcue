<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotkey_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotkey_id')->constrained()->onDelete('cascade');
            $table->string('type'); // e.g., 'switch_scene', 'toggle_mute', etc.
            $table->json('payload'); // action-specific data
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotkey_actions');
    }
};

