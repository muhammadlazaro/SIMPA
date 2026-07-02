<?php

namespace App\Http\Controllers;

use App\Http\Helpers\ApiResponse;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Ambil notifikasi milik user yang sedang login (max 30 terbaru).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $notifications = AppNotification::where('user_id', $userId)
            ->with('aplikasi:id,nama_aplikasi,nama_singkat')
            ->limit(30)
            ->get();

        $unreadCount = $notifications->where('is_read', false)->count();

        return ApiResponse::success([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = AppNotification::where('id', $id)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if (! $notification) {
            return ApiResponse::notFound('Notifikasi tidak ditemukan.');
        }

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        return ApiResponse::success(null, 'Notifikasi ditandai sudah dibaca.');
    }

    /**
     * Tandai semua notifikasi user sebagai sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::where('user_id', $request->user()->getKey())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return ApiResponse::success(null, 'Semua notifikasi ditandai sudah dibaca.');
    }
}

