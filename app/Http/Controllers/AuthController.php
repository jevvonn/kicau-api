<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
  public function register(Request $request)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|string|email|max:255|unique:users',
      'password' => 'required|string|min:8|confirmed',
      'children_age' => 'integer|min:5',
    ], [
      'name.required' => 'Nama wajib diisi.',
      'name.string' => 'Nama harus berupa teks.',
      'name.max' => 'Nama maksimal 255 karakter.',
      'email.required' => 'Email wajib diisi.',
      'email.string' => 'Email harus berupa teks.',
      'email.email' => 'Format email tidak valid.',
      'email.max' => 'Email maksimal 255 karakter.',
      'email.unique' => 'Email sudah terdaftar.',
      'password.required' => 'Password wajib diisi.',
      'password.string' => 'Password harus berupa teks.',
      'password.min' => 'Password minimal 8 karakter.',
      'password.confirmed' => 'Konfirmasi password tidak cocok.',
      'children_age.integer' => 'Umur anak harus berupa angka.',
      'children_age.min' => 'Umur anak minimal 5 tahun.',
    ]);

    $user = User::create([
      'name' => $request->name,
      'email' => $request->email,
      'password' => Hash::make($request->password),
      'children_age' => $request->children_age,
    ]);

    return response()->json([
      'message' => 'Registration successful.',
      'user' => $user,
    ], 201);
  }

  public function login(Request $request)
  {
    $request->validate([
      'email' => 'required|string|email',
      'password' => 'required|string',
    ], [
      'email.required' => 'Email wajib diisi.',
      'email.string' => 'Email harus berupa teks.',
      'email.email' => 'Format email tidak valid.',
      'password.required' => 'Password wajib diisi.',
      'password.string' => 'Password harus berupa teks.',
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
      throw ValidationException::withMessages([
        'email' => ['Email atau password yang dimasukkan salah.'],
      ]);
    }

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
      'message' => 'Login successful.',
      'token' => $token,
      'user' => $user,
    ]);
  }

  public function logout(Request $request)
  {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out successfully.']);
  }
}
