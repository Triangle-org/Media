<?php declare(strict_types=1);

namespace Triangle\Media;

use RuntimeException;

/** Downloads a public HTTP(S) image into a size-bounded temporary file. */
final class RemoteImageDownloader
{
    /** @return array{path: string, size: int, mime: string} */
    public static function download(string $url, int $maxBytes): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Remote image uploads require ext-curl.');
        }
        if ($maxBytes < 1 || strlen($url) > 2048) {
            throw new RuntimeException('Invalid remote image request.');
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Only public HTTP(S) image URLs are allowed.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(trim($parts['host'], '[]'));
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $isIpAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if (!in_array($scheme, ['http', 'https'], true)
            || ($scheme === 'http' && $port !== 80)
            || ($scheme === 'https' && $port !== 443)
            || str_ends_with($host, '.')
            || (!$isIpAddress && !preg_match('/\A[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?\z/D', $host))) {
            throw new RuntimeException('Only public HTTP(S) image URLs on standard ports are allowed.');
        }

        $addresses = self::resolvePublicAddresses($host);
        $resolvedAddress = $addresses[0];
        $resolve = [];
        if (!$isIpAddress) {
            $pinnedAddress = str_contains($resolvedAddress, ':') ? "[$resolvedAddress]" : $resolvedAddress;
            $resolve[] = "$host:$port:$pinnedAddress";
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'triangle-media-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary image file.');
        }

        $stream = fopen($temporaryPath, 'wb');
        if ($stream === false) {
            unlink($temporaryPath);
            throw new RuntimeException('Unable to open the temporary image file.');
        }

        $received = 0;
        $curl = curl_init($url);
        if ($curl === false) {
            fclose($stream);
            unlink($temporaryPath);
            throw new RuntimeException('Unable to initialize the HTTP client.');
        }

        $optionsSet = curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_NOPROXY => '*',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($stream, $maxBytes, &$received): int {
                $length = strlen($chunk);
                if ($received + $length > $maxBytes) {
                    return 0;
                }

                $written = 0;
                while ($written < $length) {
                    $chunkWritten = fwrite($stream, substr($chunk, $written));
                    if ($chunkWritten === false || $chunkWritten === 0) {
                        return 0;
                    }
                    $written += $chunkWritten;
                }

                $received += $length;
                return $length;
            },
        ]);

        try {
            if (!$optionsSet) {
                throw new RuntimeException('Unable to configure the HTTP client.');
            }
            $result = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($result === false || $status !== 200 || $received < 1) {
                throw new RuntimeException('Remote image download failed or exceeded its size limit.');
            }
        } catch (\Throwable $exception) {
            unlink($temporaryPath);
            throw $exception;
        } finally {
            curl_close($curl);
            fclose($stream);
        }

        $size = filesize($temporaryPath);
        $mime = mime_content_type($temporaryPath);
        if (!is_int($size) || $size < 1 || $size > $maxBytes || !is_string($mime)) {
            unlink($temporaryPath);
            throw new RuntimeException('The remote response is not a valid image file.');
        }

        return ['path' => $temporaryPath, 'size' => $size, 'mime' => $mime];
    }

    /** @return list<string> */
    private static function resolvePublicAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];
            foreach ($records ?: [] as $record) {
                if (isset($record['ip'])) {
                    $addresses[] = $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
            $addresses = array_values(array_unique($addresses));
        }

        if ($addresses === []) {
            throw new RuntimeException('Remote image host did not resolve to a public IP address.');
        }

        foreach ($addresses as $address) {
            if (!self::isPublicAddress($address)) {
                throw new RuntimeException('Remote image host resolves to a non-public IP address.');
            }
        }

        return $addresses;
    }

    private static function isPublicAddress(string $address): bool
    {
        $packed = @inet_pton($address);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $mappedIpv4 = inet_ntop(substr($packed, 12));
            return $mappedIpv4 !== false && self::isPublicIpv4($mappedIpv4);
        }

        if (strlen($packed) === 4) {
            return self::isPublicIpv4($address);
        }

        // Only global unicast space is accepted; special-use IPv6 blocks are excluded below.
        if ((ord($packed[0]) & 0xe0) !== 0x20) {
            return false;
        }

        return !self::isInCidr($packed, '2000::', 16)
            && !self::isInCidr($packed, '2001::', 23)
            && !self::isInCidr($packed, '2001:db8::', 32)
            && !self::isInCidr($packed, '2002::', 16)
            && !self::isInCidr($packed, '3fff::', 20);
    }

    private static function isPublicIpv4(string $address): bool
    {
        $packed = inet_pton($address);
        if ($packed === false) {
            return false;
        }

        foreach ([
            ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8],
            ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24],
            ['192.88.99.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15], ['198.51.100.0', 24],
            ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
        ] as [$network, $prefix]) {
            if (self::isInCidr($packed, $network, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private static function isInCidr(string $address, string $network, int $prefix): bool
    {
        $networkPacked = inet_pton($network);
        if ($networkPacked === false || strlen($address) !== strlen($networkPacked)) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if (substr($address, 0, $wholeBytes) !== substr($networkPacked, 0, $wholeBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;
        return (ord($address[$wholeBytes]) & $mask) === (ord($networkPacked[$wholeBytes]) & $mask);
    }
}
