<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EventImageController extends Controller
{
    /**
     * Временная загрузка изображения события
     */
    public function tmpUpload(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'required|image|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $file = $request->file('image');
            $path = $file->store('event-images/tmp', 'public');

            return response()->json([
                'image_url' => Storage::disk('public')->url($path),
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
