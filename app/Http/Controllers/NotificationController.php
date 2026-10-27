<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Auth\AlumniVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifikasi in-app (F-NTF-05/06, PRD 9.2 Notifications). Isinya: teguran Ping dari Admin untuk Staff (M4), serta
 * tiket dibuat, perubahan status, balasan Staff, dan batas thread (M5, ditulis App\Services\Tickets\TicketNotifier).
 *
 *   GET   /notifications              index    daftar notifikasi saya (terbaru di atas)
 *   PATCH /notifications/{id}/read    read     tandai dibaca, lalu buka tiketnya
 *   PATCH /notifications/read-all     readAll  tandai semua dibaca
 *
 * Setiap user hanya bisa melihat & mengubah notifikasinya sendiri (id orang lain -> 404).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $notifications = $request->user()->inAppNotifications()
            ->with('ticket')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(15);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $notifications->items()]);
        }

        return view('notifications.index', [
            'notifications' => $notifications,
            'unread' => $request->user()->inAppNotifications()->where('is_read', false)->count(),
        ]);
    }

    public function read(Request $request, int $notification): RedirectResponse|JsonResponse
    {
        $item = $request->user()->inAppNotifications()->findOrFail($notification);
        $item->update(['is_read' => true, 'read_at' => $item->read_at ?? now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $item->fresh()]);
        }

        $url = $item->ticket ? self::ticketUrl($request->user(), $item->ticket) : self::otherUrl($request->user(), $item);

        return $url ? redirect($url) : back();
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $count = $request->user()->inAppNotifications()->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['updated' => $count]]);
        }

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /** [M6] Notifikasi tanpa tiket: "Pendaftaran alumni baru" -> halaman Verifikasi Alumni (Admin). */
    public static function otherUrl(User $user, Notification $item): ?string
    {
        return $user->isAdmin() && str_starts_with($item->subject, AlumniVerificationService::REGISTERED_SUBJECT)
            ? route('admin.alumni.index')
            : null;
    }

    /** Halaman tiket yang cocok untuk peran penerima notifikasi. */
    public static function ticketUrl(User $user, Ticket $ticket): ?string
    {
        return match (true) {
            $user->isStaff() => route('staff.tickets.show', $ticket),   // tiket ditutup dialihkan ke Riwayat
            $user->isAdmin() => route('admin.tickets.show', $ticket),
            // Tiket Selesai yang belum dirating -> langsung ke form rating (F-NTF-04 "link rating").
            $user->isEndUser() && $ticket->status === TicketStatus::Selesai && ! $ticket->rating()->exists() => route('history.show', $ticket).'#rating',
            $user->isEndUser() => route('tickets.show', $ticket),
            default => null,
        };
    }
}
