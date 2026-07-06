<?php

final class SystemCommand
{
    public static function run(array $args): array
    {
        $cmd = 'doas ' . escapeshellarg(INSTALL_DIR . '/bin/llctl');
        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg((string)$arg);
        }
        exec($cmd . ' 2>&1', $output, $code);
        return ['ok' => $code === 0, 'code' => $code, 'output' => implode("\n", $output)];
    }
}
