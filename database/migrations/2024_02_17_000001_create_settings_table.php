<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('ws_host')->default('127.0.0.1');
            $table->integer('ws_port')->default(4455);
            $table->boolean('ws_secure')->default(false);
            $table->text('ws_password')->nullable(); // encrypted
            $table->boolean('auto_connect')->default(true);
            $table->timestamps();
        });

        // Insert singleton row
        DB::table('settings')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

