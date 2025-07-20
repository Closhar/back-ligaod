<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageTemplateSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImageTemplateSettingController extends Controller
{
    /**
     * Получить список настроек форматов
     */
    public function index()
    {
        try {
            $settings = ImageTemplateSetting::all();
            return response()->json($settings);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Создать новую настройку формата
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'description' => 'required|string|max:255',
                'type' => 'required|string|in:horizontal,vertical,square',
                'width' => 'required|integer|min:1',
                'height' => 'required|integer|min:1',
                'icon' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $setting = ImageTemplateSetting::create($request->all());
            return response()->json($setting, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Обновить настройку формата
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'description' => 'required|string|max:255',
                'type' => 'required|string|in:horizontal,vertical,square',
                'width' => 'required|integer|min:1',
                'height' => 'required|integer|min:1',
                'icon' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $setting = ImageTemplateSetting::findOrFail($id);
            $setting->update($request->all());
            return response()->json($setting);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Удалить настройку формата
     */
    public function destroy($id)
    {
        try {
            $setting = ImageTemplateSetting::findOrFail($id);
            $setting->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
