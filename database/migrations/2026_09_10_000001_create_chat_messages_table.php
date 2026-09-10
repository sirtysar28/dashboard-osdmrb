<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ================= PESAN / CHAT PRIVATE ANTAR PEGAWAI =================
           Satu baris = satu pesan dari pengirim (sender) ke penerima (receiver).
           Pesan masuk ditandai sudah dibaca lewat kolom read_at. */
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();   // null = belum dibaca penerima
            $table->timestamps();

            // percepat query percakapan & hitung pesan belum dibaca
            $table->index(['sender_id', 'receiver_id']);
            $table->index(['receiver_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
