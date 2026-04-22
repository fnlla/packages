<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Mail;

final class Message
{
    /** @var Address[] */
    public array $to = [];

    public function __construct(
        public Address $from,
        array $to,
        public string $subject,
        public ?string $text = null,
        public ?string $html = null
    ) {
        foreach ($to as $address) {
            if ($address instanceof Address) {
                $this->to[] = $address;
            }
        }
    }
}