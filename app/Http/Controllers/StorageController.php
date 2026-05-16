<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageController extends Controller
{
  public function upload(Request $request)
  {
    $request->validate([
      'bucket' => 'required|string|max:255|regex:/^[a-zA-Z0-9_\-\/]+$/',
      'file' => 'required|file|max:10240',
    ], [
      'bucket.required' => 'Bucket wajib diisi.',
      'bucket.string' => 'Bucket harus berupa teks.',
      'bucket.max' => 'Bucket maksimal 255 karakter.',
      'bucket.regex' => 'Bucket hanya boleh berisi huruf, angka, underscore, strip, dan slash.',
      'file.required' => 'File wajib diunggah.',
      'file.file' => 'File tidak valid.',
      'file.max' => 'Ukuran file maksimal 10MB.',
    ]);

    $bucket = trim($request->input('bucket'), '/');
    $file = $request->file('file');

    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs($bucket, $filename, 'public');

    return response()->json([
      'message' => 'File uploaded successfully.',
      'url' => Storage::url($path),
      'path' => $path,
    ], 201);
  }
}
