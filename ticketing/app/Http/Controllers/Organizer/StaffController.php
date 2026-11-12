<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\MuseumStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request, Museum $museum): Response
    {
        $this->authorize('update', $museum);

        $staff = $museum->staff()
            ->with('user:id,name,email,phone')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->user->name,
                'email' => $s->user->email,
                'phone' => $s->user->phone,
                'staff_role' => $s->staff_role,
                'is_active' => $s->is_active,
                'created_at' => $s->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Organizer/Staff/Index', [
            'museum' => $museum->only('id', 'name', 'slug'),
            'staff' => $staff,
        ]);
    }

    public function store(Request $request, Museum $museum)
    {
        $this->authorize('update', $museum);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'staff_role' => ['required', 'in:manager,gate_staff'],
            'is_active' => ['boolean'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors(['email' => 'Pengguna dengan email tersebut tidak ditemukan.']);
        }

        if ($museum->staff()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['email' => 'Pengguna sudah menjadi staf museum ini.']);
        }

        $museum->staff()->create([
            'user_id' => $user->id,
            'staff_role' => $validated['staff_role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', 'Staf berhasil ditambahkan.');
    }

    public function update(Request $request, Museum $museum, MuseumStaff $staff)
    {
        $this->authorize('update', $museum);

        $validated = $request->validate([
            'staff_role' => ['sometimes', 'in:manager,gate_staff'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $staff->update($validated);

        return back()->with('success', 'Staf berhasil diperbarui.');
    }

    public function destroy(Request $request, Museum $museum, MuseumStaff $staff)
    {
        $this->authorize('update', $museum);

        $staff->delete();

        return back()->with('success', 'Staf berhasil dihapus.');
    }
}
