<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);

    $routes->scope('/api', function (RouteBuilder $builder): void {
        $builder->setExtensions(['json']);
        $builder->get('/health', ['controller' => 'Invitations', 'action' => 'health']);
        $builder->get('/admin/auth/csrf', ['controller' => 'AdminAuth', 'action' => 'csrf']);
        $builder->get('/admin/auth/session', ['controller' => 'AdminAuth', 'action' => 'sessionStatus']);
        $builder->post('/admin/auth/login', ['controller' => 'AdminAuth', 'action' => 'login']);
        $builder->post('/admin/auth/code-login', ['controller' => 'AdminAuth', 'action' => 'codeLogin']);
        $builder->post('/admin/auth/verify', ['controller' => 'AdminAuth', 'action' => 'verify']);
        $builder->post('/admin/auth/logout', ['controller' => 'AdminAuth', 'action' => 'logout']);
        $builder->get('/admin/registrations.csv', ['controller' => 'AdminExports', 'action' => 'registrations']);
        $builder->get('/admin/events/{eventId}/registrations.zip', ['controller' => 'AdminExports', 'action' => 'registrations'])->setPass(['eventId']);
        $builder->get('/admin/surveys', ['controller' => 'AdminSurveys', 'action' => 'index']);
        $builder->get('/admin/surveys.csv', ['controller' => 'AdminSurveys', 'action' => 'export']);
        $builder->get('/admin/events', ['controller' => 'AdminEvents', 'action' => 'index']);
        $builder->post('/admin/events', ['controller' => 'AdminEvents', 'action' => 'add']);
        $builder->get('/admin/events/{id}', ['controller' => 'AdminEvents', 'action' => 'view'])->setPass(['id']);
        $builder->put('/admin/events/{id}', ['controller' => 'AdminEvents', 'action' => 'edit'])->setPass(['id']);
        $builder->delete('/admin/events/{id}', ['controller' => 'AdminEvents', 'action' => 'delete'])->setPass(['id']);
        $builder->get('/admin/events/{eventId}/pending', ['controller' => 'AdminMembers', 'action' => 'pending'])->setPass(['eventId']);
        $builder->post('/admin/events/{eventId}/pending', ['controller' => 'AdminMembers', 'action' => 'issue'])->setPass(['eventId']);
        $builder->post('/admin/events/{eventId}/pending/bulk', ['controller' => 'AdminMembers', 'action' => 'bulkIssue'])->setPass(['eventId']);
        $builder->post('/admin/events/{eventId}/pending/{memberId}/reissue', ['controller' => 'AdminMembers', 'action' => 'reissue'])->setPass(['eventId', 'memberId']);
        $builder->get('/admin/events/{eventId}/members', ['controller' => 'AdminMembers', 'action' => 'completed'])->setPass(['eventId']);
        $builder->get('/admin/events/{eventId}/members/{memberId}', ['controller' => 'AdminMembers', 'action' => 'viewCompleted'])->setPass(['eventId', 'memberId']);
        $builder->get('/invitations/{token}', ['controller' => 'Invitations', 'action' => 'view'])
            ->setPass(['token']);
        $builder->post('/invitations/{token}/validate', ['controller' => 'Invitations', 'action' => 'validateRegistration'])
            ->setPass(['token']);
        $builder->post('/invitations/{token}/submit', ['controller' => 'Invitations', 'action' => 'submit'])
            ->setPass(['token']);
        $builder->get('/surveys/options', ['controller' => 'Surveys', 'action' => 'options']);
        $builder->post('/surveys/responses', ['controller' => 'Surveys', 'action' => 'submit']);
    });
};
