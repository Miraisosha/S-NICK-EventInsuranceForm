<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->get('/health', ['controller' => 'Invitations', 'action' => 'health']);
        $builder->get('/admin/registrations.csv', ['controller' => 'AdminExports', 'action' => 'registrations']);
        $builder->get('/admin/events', ['controller' => 'AdminEvents', 'action' => 'index']);
        $builder->post('/admin/events', ['controller' => 'AdminEvents', 'action' => 'add']);
        $builder->get('/invitations/{token}', ['controller' => 'Invitations', 'action' => 'view'])
            ->setPass(['token']);
        $builder->post('/invitations/{token}/validate', ['controller' => 'Invitations', 'action' => 'validateRegistration'])
            ->setPass(['token']);
        $builder->post('/invitations/{token}/submit', ['controller' => 'Invitations', 'action' => 'submit'])
            ->setPass(['token']);
    });
};
