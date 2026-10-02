<?php

declare(strict_types=1);

namespace Lahatre\Iam\Integrations;

use Google\Auth\AccessToken;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lahatre\Iam\Exceptions\GoogleAuthException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GoogleIdentityVerifier
{
    /** @return array{issuer: string, subject: string, email: string, authoritative_email: bool, first_name: ?string, last_name: ?string, nonce: string} */
    public function verify(string $credential): array
    {
        $clientId = config('services.google.client_id');
        if (!is_string($clientId) || $clientId === '') {
            throw GoogleAuthException::configurationMissing();
        }
        $verifier = new AccessToken(fn (RequestInterface $request): ResponseInterface => $this->certificates($request));
        try {
            $claims = $verifier->verify($credential, ['audience' => $clientId]);
        } catch (\InvalidArgumentException|\UnexpectedValueException $exception) {
            throw GoogleAuthException::invalidCredential();
        } catch (\RuntimeException $exception) {
            throw GoogleAuthException::verificationUnavailable();
        }
        if (!is_array($claims)
            || !is_string($claims['sub'] ?? null) || !preg_match('/\A[\x21-\x7E]{1,255}\z/', $claims['sub'])
            || !is_string($claims['email'] ?? null) || strlen($claims['email']) > 254
            || filter_var($claims['email'], FILTER_VALIDATE_EMAIL) === false
            || ($claims['email_verified'] ?? null) !== true
            || !is_string($claims['nonce'] ?? null) || $claims['nonce'] === ''
            || !is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
            || ($claims['aud'] ?? null) !== $clientId
            || (isset($claims['azp']) && $claims['azp'] !== $clientId)
            || !in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw GoogleAuthException::invalidCredential();
        }
        $email = Str::normalize($claims['email']);

        return [
            'issuer'              => 'https://accounts.google.com', 'subject' => $claims['sub'], 'email' => $email,
            'authoritative_email' => str_ends_with($email, '@gmail.com')
                || (is_string($claims['hd'] ?? null) && $claims['hd'] !== ''),
            'first_name' => $this->profileName($claims['given_name'] ?? null),
            'last_name'  => $this->profileName($claims['family_name'] ?? null), 'nonce' => $claims['nonce'],
        ];
    }

    private function profileName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $name = Str::sanitize($value);

        return $name !== '' && mb_strlen($name) <= 100 ? $name : null;
    }

    /** Cache Google's public keys according to their HTTP lifetime across PHP processes. */
    private function certificates(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $key = 'iam:google:certificates';
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return new Response(200, ['Cache-Control' => 'max-age=60'], $cached['body']);
        }
        try {
            $response = Http::timeout(5)->get($url);
        } catch (ConnectionException) {
            throw GoogleAuthException::verificationUnavailable();
        }
        $body = $response->body();
        if (!$response->successful() || !is_array($response->json('keys')) || $response->json('keys') === []) {
            throw GoogleAuthException::verificationUnavailable();
        }
        $seconds = preg_match('/(?:^|,)\s*max-age=(\d+)/i', $response->header('Cache-Control'), $matches)
            ? min((int) $matches[1], 86400) : 3600;
        Cache::put($key, ['body' => $body], $seconds);

        return new Response(200, ['Cache-Control' => 'max-age='.$seconds], $body);
    }
}
