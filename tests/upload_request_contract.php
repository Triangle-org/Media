<?php declare(strict_types=1);

/**
 * @package     Triangle Media Plugin Tests
 * @link        https://github.com/Triangle-org/Media
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Triangle\Engine {
    final class File
    {
        public function __construct(private string $name, private bool $valid = true) {}

        public function getUploadName(): string
        {
            return $this->name;
        }

        public function isValid(): bool
        {
            return $this->valid;
        }
    }
}

namespace support {
    final class Request
    {
        public function __construct(
            private string $requestMethod,
            private array $formData = [],
            private ?\Triangle\Engine\File $uploadedFile = null,
            private ?string $requestIp = '203.0.113.12',
        ) {}

        public function method(): string
        {
            return $this->requestMethod;
        }

        public function file(?string $name = null): array|\Triangle\Engine\File|null
        {
            return $name === 'file' ? $this->uploadedFile : [];
        }

        public function post(string $name, mixed $default = null): mixed
        {
            return $this->formData[$name] ?? $default;
        }

        public function getRequestIp(): ?string
        {
            return $this->requestIp;
        }
    }
}

namespace Triangle\Media {
    final class RateLimiter
    {
        public static bool $enabled = false;
        public static array $keys = [];

        public static function instance(): self
        {
            return new self();
        }

        public function isEnabled(): bool
        {
            return self::$enabled;
        }

        public function check(string $key): bool
        {
            self::$keys[] = $key;
            return true;
        }

        public function errorMessage(): string
        {
            return 'rate limited';
        }
    }

    final class Uploader
    {
        public static array $calls = [];

        public function __construct(public string $projectId, public ?string $authorIp = null) {}

        public function uploadFile(\Triangle\Engine\File $file): array
        {
            self::$calls[] = ['file', $this->projectId, $this->authorIp, $file];
            return [
                'link' => 'https://media.example.test/image.png',
                'mime' => 'image/png',
                'width' => 10,
                'height' => 20,
                'color' => '#ffffff',
                'size' => 100,
            ];
        }

        public function uploadLink(string $link): array
        {
            self::$calls[] = ['link', $this->projectId, $this->authorIp, $link];
            return [
                'link' => 'https://media.example.test/image.png',
                'mime' => 'image/png',
                'width' => 10,
                'height' => 20,
                'color' => '#ffffff',
                'size' => 100,
            ];
        }
    }
}

namespace {
    final class TestResponse
    {
        public function __construct(public mixed $data, public int $status) {}
    }

    final class TestDatabase
    {
        public function where(string $field, string $value): self
        {
            return $this;
        }

        public function getOne(string $table): array
        {
            return ['id' => 'project-123'];
        }
    }

    function config(string $key, mixed $default = null): mixed
    {
        return $key === 'plugin.triangle.media.app.database.projects' ? 'Media_Projects' : $default;
    }

    function response(mixed $data = '', int $status = 200): TestResponse
    {
        return new TestResponse($data, $status);
    }

    function db(): TestDatabase
    {
        return new TestDatabase();
    }

    function expect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    require dirname(__DIR__) . '/src/plugin/media/app/controller/Upload.php';

    $controller = new \plugin\media\app\controller\Upload();
    $file = new \Triangle\Engine\File('upload.png');
    \Triangle\Media\RateLimiter::$enabled = true;

    $response = $controller->index(new \support\Request('POST', ['token' => 'project-token'], $file));
    expect($response instanceof TestResponse && $response->status === 200, 'A valid request file should be accepted.');
    expect(\Triangle\Media\Uploader::$calls[0] === ['file', 'project-123', '203.0.113.12', $file], 'The controller must pass the request file and resolved IP to the uploader.');
    expect(\Triangle\Media\RateLimiter::$keys === ['RATELIMITER_CLIENT_203.0.113.12'], 'Upload rate limits must use the Engine request IP.');

    $invalidFile = new \Triangle\Engine\File('upload.png', false);
    $invalid = $controller->index(new \support\Request('POST', ['token' => 'project-token'], $invalidFile));
    expect($invalid instanceof TestResponse && $invalid->status === 400, 'Invalid uploads must be rejected before processing.');
    expect(count(\Triangle\Media\Uploader::$calls) === 1, 'Invalid uploads must not reach the uploader.');

    $linkResponse = $controller->index(new \support\Request('POST', [
        'token' => 'project-token',
        'link' => ' https://images.example.test/photo.png ',
    ]));
    expect($linkResponse instanceof TestResponse && $linkResponse->status === 200, 'A valid image link should be accepted.');
    expect(\Triangle\Media\Uploader::$calls[1] === ['link', 'project-123', '203.0.113.12', 'https://images.example.test/photo.png'], 'The link and request IP must come from the current request.');

    echo "Upload request contract checks passed.\n";
}
