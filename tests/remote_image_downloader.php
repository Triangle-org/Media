<?php declare(strict_types=1);

require dirname(__DIR__) . '/src/RemoteImageDownloader.php';

use Triangle\Media\RemoteImageDownloader;

$blockedUrls = [
    'file:///etc/passwd',
    'http://user:pass@example.com/image.png',
    'http://example.com:8080/image.png',
    'http://127.0.0.1/image.png',
    'http://10.0.0.1/image.png',
    'http://100.64.0.1/image.png',
    'http://198.18.0.1/image.png',
    'http://224.0.0.1/image.png',
    'http://[::1]/image.png',
    'http://[::ffff:127.0.0.1]/image.png',
    'http://[2001:db8::1]/image.png',
    'http://[2000::1]/image.png',
    'http://localhost/image.png',
];

foreach ($blockedUrls as $url) {
    try {
        RemoteImageDownloader::download($url, 1024);
    } catch (RuntimeException) {
        continue;
    }

    throw new RuntimeException("Expected remote URL to be rejected: $url");
}

echo "Remote image URL security checks passed.\n";
