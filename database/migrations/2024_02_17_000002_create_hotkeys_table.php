<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotkeys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('accelerator'); // normalized
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            // Unique constraint on enabled accelerators checked at application level
            $table->index(['enabled', 'accelerator']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotkeys');
    }
};

