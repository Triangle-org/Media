<?php declare(strict_types=1);

/**
 * @package     Triangle Media Plugin Tests
 * @link        https://github.com/Triangle-org/Media
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright © 2016-2026 Localzet Group. All rights reserved.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace support {
    final class Request
    {
        public function __construct(
            private string $requestMethod,
            private array $formData = [],
            private ?string $requestIp = '203.0.113.10',
        ) {}

        public function method(): string
        {
            return $this->requestMethod;
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
}

namespace {
    final class TestResponse
    {
        public function __construct(public mixed $data, public int $status) {}
    }

    final class TestDatabase
    {
        public array $inserted = [];

        public function insert(string $table, array $data): void
        {
            $this->inserted[] = [$table, $data];
        }
    }

    $GLOBALS['project_registration_enabled'] = false;
    $GLOBALS['test_database'] = new TestDatabase();

    function config(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'plugin.triangle.media.app.project_registration' => $GLOBALS['project_registration_enabled'],
            'plugin.triangle.media.app.database.projects' => 'Media_Projects',
            default => $default,
        };
    }

    function response(mixed $data = '', int $status = 200): TestResponse
    {
        return new TestResponse($data, $status);
    }

    function view(string $name): string
    {
        return $name;
    }

    function generateId(): string
    {
        return 'test-project-token';
    }

    function db(): TestDatabase
    {
        return $GLOBALS['test_database'];
    }

    function expect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    require dirname(__DIR__) . '/src/plugin/media/app/controller/Project.php';

    $controller = new \plugin\media\app\controller\Project();
    $disabled = $controller->index(new \support\Request('POST', ['name' => 'blocked']));
    expect($disabled instanceof TestResponse && $disabled->status === 403, 'Disabled registration must reject requests.');
    expect($GLOBALS['test_database']->inserted === [], 'Disabled registration must not create a project.');

    $GLOBALS['project_registration_enabled'] = true;
    \Triangle\Media\RateLimiter::$enabled = true;
    $invalid = $controller->index(new \support\Request('POST', ['name' => str_repeat('x', 121)]));
    expect($invalid instanceof TestResponse && $invalid->status === 422, 'Invalid registration data must be rejected.');
    expect($GLOBALS['test_database']->inserted === [], 'Invalid registration data must not create a project.');
    expect(\Triangle\Media\RateLimiter::$keys === ['MEDIA_PROJECT_REGISTER_203.0.113.10'], 'Registration rate limits must use the Engine request IP.');

    $valid = $controller->index(new \support\Request('POST', [
        'name' => '  Example project  ',
        'description' => 'Image uploads',
        'email' => 'owner@example.test',
    ]));
    expect($valid instanceof TestResponse && $valid->status === 200, 'Valid registration must succeed.');
    expect($valid->data === ['token' => 'test-project-token'], 'Registration must return the generated token.');
    expect($GLOBALS['test_database']->inserted[0][1]['name'] === 'Example project', 'Project names must be trimmed.');

    echo "Project registration checks passed.\n";
}
