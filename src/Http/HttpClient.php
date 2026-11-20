<?php
declare(strict_types=1);

namespace App\Http;

class HttpClient
{
    /** @return array<string, mixed> */
    public function get(string $url): array
    {
        $exception = new \RuntimeException('HTTP request failed after retries.');
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $this->request($url);
            } catch (\RuntimeException $caught) {
                $exception = $caught;
                if ($attempt < 3) {
                    usleep(250000 * $attempt);
                }
            }
        }
        throw $exception;
    }

    /** @return array<string, mixed> */
    private function request(string $url): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: AgroWeatherForecast/1.0'],
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException(sprintf('HTTP request failed (status %d): %s', $status, $error ?: substr((string) $body, 0, 300)));
        }
        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Provider returned invalid JSON.', 0, $exception);
        }
    }
}
