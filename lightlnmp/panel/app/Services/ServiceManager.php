<?php

final class ServiceManager
{
    public function statuses(): array
    {
        $services = ['nginx', 'php-fpm', 'mariadb', 'crond'];
        $result = [];
        foreach ($services as $service) {
            $run = SystemCommand::run(['service', 'status', $service]);
            $result[$service] = $run['ok'] ? 'running' : 'stopped';
        }
        return $result;
    }

    public function action(string $service, string $action): array
    {
        return SystemCommand::run(['service', $action, $service]);
    }
}
