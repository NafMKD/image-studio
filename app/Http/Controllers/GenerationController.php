<?php

namespace App\Http\Controllers;

use App\Services\GenerationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GenerationController extends Controller
{
    public function store(Request $request, GenerationManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'client_request_id' => ['required', 'uuid'],
            'conversation_id' => ['nullable', 'integer'],
            'prompt' => ['required', 'string', 'min:3', 'max:4000'],
+            'size' => ['required', 'in:1024x1024,1536x1024,1024x1536'],
+            'quality' => ['required', 'in:low,medium,high'],
+            'reference_urls' => ['nullable', 'string', 'max:4000'],
        ]);
        $urls = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $data['reference_urls'] ?? ''))));
        Validator::make(['urls' => $urls], ['urls' => ['array', 'max:4']])->validate();

        foreach ($urls as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            $ip = filter_var($host, FILTER_VALIDATE_IP);
            $publicIp = ! $ip || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
                || ! $host || ! $publicIp || in_array(strtolower($host), ['localhost'], true)
                || str_ends_with(strtolower($host), '.local') || str_ends_with(strtolower($host), '.internal')
                || parse_url($url, PHP_URL_USER) !== null) {
                throw ValidationException::withMessages([
                    'reference_urls' => 'References must be public HTTP(S) image URLs.',
                ]);
            }
        }

        $data['reference_urls'] = $urls;
        $generation = $manager->start($request->user(), $data);

        return redirect()->route('studio.show', ['conversation' => $generation->conversation_id]);
    }
}
