<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console;

use PDO;

interface SeederInterface
{
    public function run(PDO $pdo): void;
}
