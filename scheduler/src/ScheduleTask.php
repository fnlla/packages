<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Scheduler;

final class ScheduleTask
{
    private int $intervalSeconds = 60;
    private ?int $dailyAtSeconds = null;
    private bool $background = false;
    /** @var callable */
    private $handler;

    public function __construct(private string $name, callable $handler)
    {
        $this->handler = $handler;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function handler(): callable
    {
        return $this->handler;
    }

    public function everySeconds(int $seconds): self
    {
        $this->dailyAtSeconds = null;
        $this->intervalSeconds = max(1, $seconds);
        return $this;
    }

    public function everyMinute(): self
    {
        return $this->everySeconds(60);
    }

    public function hourly(): self
    {
        return $this->everySeconds(3600);
    }

    public function daily(): self
    {
        return $this->everySeconds(86400);
    }

    public function dailyAt(string $time): self
    {
        $seconds = $this->parseTime($time);
        if ($seconds === null) {
            throw new \RuntimeException('Invalid time for dailyAt: ' . $time);
        }
        $this->dailyAtSeconds = $seconds;
        return $this;
    }

    public function runInBackground(bool $enabled = true): self
    {
        $this->background = $enabled;
        return $this;
    }

    public function runsInBackground(): bool
    {
        return $this->background;
    }

    public function intervalSeconds(): int
    {
        return $this->intervalSeconds;
    }

    public function isDue(\DateTimeImmutable $now, ?int $lastRun): bool
    {
        if ($this->dailyAtSeconds !== null) {
            $hour = intdiv($this->dailyAtSeconds, 3600);
            $minute = intdiv($this->dailyAtSeconds % 3600, 60);
            $target = $now->setTime($hour, $minute, 0);
            if ($now < $target) {
                return false;
            }

            if ($lastRun === null) {
                return true;
            }

            $last = (new \DateTimeImmutable('@' . $lastRun))->setTimezone($now->getTimezone());
            return $last < $target;
        }

        if ($lastRun === null) {
            return true;
        }

        return ($now->getTimestamp() - $lastRun) >= $this->intervalSeconds;
    }

    private function parseTime(string $time): ?int
    {
        $time = trim($time);
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        return $hour * 3600 + $minute * 60;
    }
}
