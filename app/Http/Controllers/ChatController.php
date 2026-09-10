<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pesan / chat private antar pegawai.
 * Widget melayang ada di pojok kanan bawah setiap halaman (setelah login).
 */
class ChatController extends Controller
{
    /**
     * Daftar kontak (semua pengguna aktif selain diri sendiri) +
     * pesan terakhir + jumlah pesan belum dibaca + pencarian nama.
     */
    public function contacts(Request $request): JsonResponse
    {
        $me = $request->user();
        $search = trim((string) $request->query('q'));

        // pengguna yang sedang DARING = punya sesi aktif ≤ 5 menit terakhir
        $onlineIds = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values();

        $contacts = User::query()
            ->where('id', '!=', $me->id)
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->with('employee:id,name,position_name')
            ->orderBy('name')
            ->limit(60)
            ->get();

        // pesan terakhir tiap kontak (ambil 500 pesan terbaru yang melibatkan saya,
        // lalu ambil yang paling baru untuk tiap lawan bicara)
        $lastMessages = ChatMessage::query()
            ->where(fn ($q) => $q->where('sender_id', $me->id)->orWhere('receiver_id', $me->id))
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn ($m) => [
                ($m->sender_id === $me->id ? $m->receiver_id : $m->sender_id) => $m,
            ]);

        $unread = ChatMessage::unreadFor($me->id)
            ->select('sender_id', DB::raw('count(*) as total'))
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        return response()->json([
            'contacts' => $contacts->map(fn (User $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'initials' => $contact->initials,
                'role' => $contact->role_label,
                'position' => $contact->employee?->position_name,
                'online' => $onlineIds->contains($contact->id),
                'last_message' => ($last = $lastMessages->get($contact->id)) ? [
                    'text' => \Illuminate\Support\Str::limit($last->message, 60),
                    'mine' => $last->sender_id === $me->id,
                    'time' => $last->created_at->timezone(config('app.timezone'))->translatedFormat('H:i'),
                    'date' => $last->created_at->timezone(config('app.timezone'))->translatedFormat('d M'),
                ] : null,
                'unread' => (int) ($unread[$contact->id] ?? 0),
            ])->values(),
        ]);
    }

    /**
     * Riwayat percakapan dengan satu pengguna (otomatis tandai sudah dibaca).
     */
    public function messages(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        ChatMessage::markConversationRead($me->id, $user->id);

        $messages = ChatMessage::conversation($me->id, $user->id)
            ->orderBy('created_at')
            ->limit(300)
            ->get();

        return response()->json([
            'partner' => [
                'id' => $user->id,
                'name' => $user->name,
                'initials' => $user->initials,
                'role' => $user->role_label,
                'position' => $user->employee?->position_name,
            ],
            'messages' => $messages->map(fn (ChatMessage $message) => $this->messageJson($message, $me->id)),
        ]);
    }

    /**
     * Kirim pesan ke satu pengguna.
     */
    public function store(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ], [
            'message.required' => 'Isi pesan terlebih dahulu.',
            'message.max' => 'Pesan maksimal 2000 karakter.',
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak dapat mengirim pesan ke diri sendiri.'], 422);
        }

        $message = ChatMessage::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $user->id,
            'message' => trim($validated['message']),
        ]);

        return response()->json([
            'message' => $this->messageJson($message, $request->user()->id),
        ]);
    }

    /**
     * Total pesan belum dibaca (untuk badge merah pada tombol chat).
     */
    public function unread(Request $request): JsonResponse
    {
        return response()->json([
            'total' => ChatMessage::unreadFor($request->user()->id)->count(),
        ]);
    }

    /* ================= HELPER ================= */

    private function messageJson(ChatMessage $message, int $myId): array
    {
        return [
            'id' => $message->id,
            'text' => $message->message,
            'mine' => $message->sender_id === $myId,
            'read' => $message->read_at !== null,
            'time' => $message->created_at->timezone(config('app.timezone'))->translatedFormat('H:i'),
        ];
    }
}
