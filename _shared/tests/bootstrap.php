<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

require dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'ensure-php85.php';

$FnllaRoot = dirname(__DIR__, 3);

$autoloadCandidates = [
    $FnllaRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'harness' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    $FnllaRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
    $FnllaRoot . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'starter' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        $autoloadLoaded = true;
        break;
    }
}

spl_autoload_register(function (string $class) use ($FnllaRoot): void {
    $prefix = 'Fnlla\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    $frameworkPath = $FnllaRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relativePath;
    if (is_file($frameworkPath)) {
        require_once $frameworkPath;
        return;
    }

    $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
    $packageClass = $parts[0] ?? '';
    $package = strtolower($packageClass);
    if ($package === '') {
        return;
    }

    $packagePath = $FnllaRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
        . implode(DIRECTORY_SEPARATOR, array_slice($parts, 1));
    if (is_file($packagePath)) {
        require_once $packagePath;
        return;
    }

    if ($packageClass !== '') {
        $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $packageClass) ?? $packageClass);
        $kebabPath = $FnllaRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $kebab . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, array_slice($parts, 1));
        if (is_file($kebabPath)) {
            require_once $kebabPath;
        }
    }
});
