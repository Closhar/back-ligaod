<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactAddress;
use App\Models\ContactEmail;
use App\Models\ContactMessage;
use App\Models\ContactPage;
use App\Models\ContactPhone;
use App\Models\ContactSocial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function publicData(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->payload(false),
        ]);
    }

    public function admin(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ...$this->payload(true),
                'messages' => ContactMessage::query()->latest()->limit(200)->get(),
                'unprocessed_count' => ContactMessage::where('is_processed', false)->count(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page.title' => ['required', 'string', 'max:255'],
            'page.description' => ['nullable', 'string'],
            'page.notify_email_enabled' => ['boolean'],
            'page.notify_email_to' => ['nullable', 'string', 'max:255'],
            'page.notify_telegram_enabled' => ['boolean'],
            'page.notify_telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'page.notify_telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'addresses' => ['array'],
            'phones' => ['array'],
            'emails' => ['array'],
            'socials' => ['array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $page = $this->page();
        $page->update([
            'title' => $request->input('page.title'),
            'description' => $request->input('page.description'),
            'notify_email_enabled' => $request->boolean('page.notify_email_enabled'),
            'notify_email_to' => $request->input('page.notify_email_to'),
            'notify_telegram_enabled' => $request->boolean('page.notify_telegram_enabled'),
            'notify_telegram_bot_token' => $request->input('page.notify_telegram_bot_token'),
            'notify_telegram_chat_id' => $request->input('page.notify_telegram_chat_id'),
        ]);

        $this->replaceItems(ContactAddress::class, $this->normalizeAddresses($request->input('addresses', [])));
        $this->replaceItems(ContactPhone::class, $this->normalizeSimple($request->input('phones', []), ['title', 'phone']));
        $this->replaceItems(ContactEmail::class, $this->normalizeSimple($request->input('emails', []), ['title', 'email']));
        $this->replaceItems(ContactSocial::class, $this->normalizeSimple($request->input('socials', []), ['title', 'icon', 'url']));

        return $this->admin();
    }

    public function storeMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $message = ContactMessage::create([
            ...$validator->validated(),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        $this->notify($message);

        return response()->json([
            'success' => true,
            'message' => 'Обращение отправлено',
            'data' => $message,
        ], 201);
    }

    public function updateMessage(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $isProcessed = $request->boolean('is_processed');
        $contactMessage->update([
            'is_processed' => $isProcessed,
            'processed_at' => $isProcessed ? now() : null,
        ]);

        return response()->json(['success' => true, 'data' => $contactMessage->fresh()]);
    }

    public function destroyMessage(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json(['success' => true]);
    }

    private function payload(bool $includeSecrets): array
    {
        $page = $this->page();
        $pageData = $page->toArray();
        if (! $includeSecrets) {
            unset($pageData['notify_telegram_bot_token'], $pageData['notify_telegram_chat_id']);
        }

        return [
            'page' => $pageData,
            'addresses' => ContactAddress::orderByDesc('is_main')->orderBy('sort_order')->orderBy('id')->get(),
            'phones' => ContactPhone::orderBy('sort_order')->orderBy('id')->get(),
            'emails' => ContactEmail::orderBy('sort_order')->orderBy('id')->get(),
            'socials' => ContactSocial::orderBy('sort_order')->orderBy('id')->get(),
            'unprocessed_count' => ContactMessage::where('is_processed', false)->count(),
        ];
    }

    private function page(): ContactPage
    {
        return ContactPage::firstOrCreate([], [
            'title' => 'Контакты',
            'description' => 'Свяжитесь с нами удобным способом.',
        ]);
    }

    private function normalizeAddresses(array $items): array
    {
        $mainIndex = collect($items)->search(fn ($item) => filter_var($item['is_main'] ?? false, FILTER_VALIDATE_BOOLEAN));

        return collect($items)->values()->map(function ($item, $index) use ($mainIndex) {
            $latitude = $item['latitude'] ?? null;
            $longitude = $item['longitude'] ?? null;

            return [
                'title' => $item['title'] ?? null,
                'address' => $item['address'] ?? '',
                'latitude' => $latitude !== '' ? $latitude : null,
                'longitude' => $longitude !== '' ? $longitude : null,
                'is_main' => $mainIndex === false ? $index === 0 : $index === $mainIndex,
                'sort_order' => (int) ($item['sort_order'] ?? ($index + 1) * 10),
            ];
        })->filter(fn ($item) => trim($item['address']) !== '')->values()->all();
    }

    private function normalizeSimple(array $items, array $fields): array
    {
        return collect($items)->values()->map(function ($item, $index) use ($fields) {
            $row = ['sort_order' => (int) ($item['sort_order'] ?? ($index + 1) * 10)];
            foreach ($fields as $field) {
                $row[$field] = $item[$field] ?? ($field === 'icon' ? 'mdi:link-variant' : null);
            }
            return $row;
        })->filter(function ($item) use ($fields) {
            $required = collect($fields)->last();
            return trim((string) ($item[$required] ?? '')) !== '';
        })->values()->all();
    }

    private function replaceItems(string $modelClass, array $items): void
    {
        $modelClass::query()->delete();
        foreach ($items as $item) {
            $modelClass::create($item);
        }
    }

    private function notify(ContactMessage $message): void
    {
        $page = $this->page();
        $text = "Новое обращение с сайта\n\n"
            ."Имя: {$message->name}\n"
            ."Email: ".($message->email ?: '-')."\n"
            ."Телефон: ".($message->phone ?: '-')."\n"
            ."Тема: {$message->subject}\n\n"
            .$message->message;

        if ($page->notify_email_enabled && $page->notify_email_to) {
            try {
                Mail::raw($text, fn ($mail) => $mail->to($page->notify_email_to)->subject('Новое обращение с сайта'));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($page->notify_telegram_enabled && $page->notify_telegram_bot_token && $page->notify_telegram_chat_id) {
            try {
                Http::asForm()->post("https://api.telegram.org/bot{$page->notify_telegram_bot_token}/sendMessage", [
                    'chat_id' => $page->notify_telegram_chat_id,
                    'text' => $text,
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
