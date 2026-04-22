<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Testing;

use Fnlla\Http\Response;
use Fnlla\Session\SessionInterface;
use PHPUnit\Framework\Assert;

final class TestResponse
{
    public function __construct(
        private Response $response,
        private ?SessionInterface $session = null
    )
    {
    }

    public function response(): Response
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    public function json(): array
    {
        $decoded = json_decode($this->body(), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function assertStatus(int $status): self
    {
        Assert::assertSame($status, $this->status(), 'Expected status ' . $status . ', got ' . $this->status());
        return $this;
    }

    public function assertJson(array $subset): self
    {
        $data = $this->json();
        Assert::assertNotSame([], $data, 'Response body is not valid JSON.');
        Assert::assertTrue($this->arraySubset($subset, $data), 'JSON subset assertion failed.');
        return $this;
    }

    public function assertRedirect(?string $location = null): self
    {
        $status = $this->status();
        Assert::assertContains($status, [301, 302, 303, 307, 308], 'Expected redirect status, got ' . $status);
        if ($location !== null) {
            $header = $this->response->getHeaderLine('Location');
            Assert::assertSame($location, $header, 'Expected redirect to ' . $location . ', got ' . $header);
        }
        return $this;
    }

    public function assertSessionHasErrors(array|string $keys, string $bag = 'default'): self
    {
        Assert::assertInstanceOf(SessionInterface::class, $this->session, 'Session is not available.');

        $errors = $this->session->get('_Fnlla_errors', []);
        $bagName = $this->session->get('_Fnlla_error_bag', 'default');

        Assert::assertSame($bag, $bagName, 'Expected error bag ' . $bag . ', got ' . $bagName);

        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            Assert::assertIsArray($errors, 'Session errors should be an array.');
            Assert::assertArrayHasKey($key, $errors, 'Expected session error for key: ' . $key);
        }

        return $this;
    }

    public function assertSessionHasOld(array|string $keys): self
    {
        Assert::assertInstanceOf(SessionInterface::class, $this->session, 'Session is not available.');

        $old = $this->session->get('_Fnlla_old', []);
        Assert::assertIsArray($old, 'Old input is not available.');

        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            Assert::assertArrayHasKey($key, $old, 'Expected old input for key: ' . $key);
        }

        return $this;
    }

    private function arraySubset(array $subset, array $data): bool
    {
        foreach ($subset as $key => $value) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
            if (is_array($value)) {
                if (!is_array($data[$key]) || !$this->arraySubset($value, $data[$key])) {
                    return false;
                }
                continue;
            }
            if ($data[$key] !== $value) {
                return false;
            }
        }
        return true;
    }
}
