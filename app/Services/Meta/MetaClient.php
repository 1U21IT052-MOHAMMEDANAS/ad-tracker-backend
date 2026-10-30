<?php

namespace App\Services\Meta;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Thin wrapper around the Meta Graph API (Marketing API). */
class MetaClient
{
    private string $token;

    private ?string $secret;

    private string $baseUrl;

    public function __construct()
    {
        $config = config('services.meta');
        $this->token = (string) $config['access_token'];
        $this->secret = $config['app_secret'] ?: null;
        $this->baseUrl = 'https://graph.facebook.com/'.$config['api_version'].'/';
    }

    public function configured(): bool
    {
        return $this->token !== '';
    }

    public function get(string $path, array $params = []): array
    {
        if (! $this->configured()) {
            throw new MetaApiException('META_ACCESS_TOKEN is not set in backend/.env');
        }

        $params['access_token'] = $this->token;
        if ($this->secret) {
            $params['appsecret_proof'] = hash_hmac('sha256', $this->token, $this->secret);
        }

        try {
            $response = Http::timeout(60)
                ->retry(2, 1000, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()), throw: false)
                ->get($this->baseUrl.ltrim($path, '/'), $params);
        } catch (ConnectionException $e) {
            throw new MetaApiException('Could not reach Meta: '.$e->getMessage());
        }

        $json = $response->json() ?? [];
        if (isset($json['error'])) {
            throw new MetaApiException('Meta API: '.($json['error']['message'] ?? 'unknown error'), (int) ($json['error']['code'] ?? 0));
        }
        if ($response->failed()) {
            throw new MetaApiException('Meta API returned HTTP '.$response->status());
        }

        return $json;
    }

    /** Follows paging cursors and returns every row of a list edge. */
    public function getAll(string $path, array $params = []): array
    {
        $params['limit'] ??= 500;
        $rows = [];

        do {
            $page = $this->get($path, $params);
            array_push($rows, ...($page['data'] ?? []));
            $params['after'] = $page['paging']['cursors']['after'] ?? null;
        } while (isset($page['paging']['next']) && $params['after']);

        return $rows;
    }

    /** Token validity and expiry, checked with the app token (APP_ID|APP_SECRET). */
    public function tokenInfo(): array
    {
        $config = config('services.meta');
        if (! $this->configured() || ! $config['app_id'] || ! $this->secret) {
            return ['configured' => false];
        }

        $response = Http::timeout(30)->get($this->baseUrl.'debug_token', [
            'input_token' => $this->token,
            'access_token' => $config['app_id'].'|'.$this->secret,
        ]);
        $data = $response->json('data') ?? [];

        return [
            'configured' => true,
            'valid' => (bool) ($data['is_valid'] ?? false),
            'expires_at' => ! empty($data['expires_at']) ? date(DATE_ATOM, $data['expires_at']) : null,
            'scopes' => $data['scopes'] ?? [],
            'error' => $data['error']['message'] ?? $response->json('error.message'),
        ];
    }
}
