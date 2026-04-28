<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
  public function profile(Request $request)
  {
    return response()->json($request->user());
  }

  public function updateProfile(Request $request)
  {
    $user = $request->user();

    $request->validate([
      'name' => 'sometimes|string|max:255',
      'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
    ], [
      'name.string' => 'Nama harus berupa teks.',
      'name.max' => 'Nama maksimal 255 karakter.',
      'email.string' => 'Email harus berupa teks.',
      'email.email' => 'Format email tidak valid.',
      'email.max' => 'Email maksimal 255 karakter.',
      'email.unique' => 'Email sudah digunakan oleh pengguna lain.',
    ]);

    if ($request->filled('name')) {
      $user->name = $request->name;
    }

    if ($request->filled('email')) {
      $user->email = $request->email;
    }

    if ($request->filled('password')) {
      $user->password = Hash::make($request->password);
    }

    $user->save();

    return response()->json([
      'message' => 'Profile updated successfully.',
      'user' => $user,
    ]);
  }

  public function updateAvatar(Request $request)
  {
    $request->validate([
      'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    ], [
      'avatar.required' => 'File avatar wajib diunggah.',
      'avatar.image' => 'File harus berupa gambar.',
      'avatar.mimes' => 'Format gambar harus jpg, jpeg, png, atau webp.',
      'avatar.max' => 'Ukuran gambar maksimal 2MB.',
    ]);

    $user = $request->user();

    // Delete old avatar if exists
    if ($user->avatar) {
      $oldPath = str_replace('/storage/', 'public/', parse_url($user->avatar, PHP_URL_PATH));
      Storage::delete($oldPath);
    }

    $path = $request->file('avatar')->store('avatars', 'public');
    $user->avatar = Storage::url($path);
    $user->save();

    return response()->json([
      'message' => 'Avatar berhasil diperbarui.',
      'user' => $user,
    ]);
  }
}
