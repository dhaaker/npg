<?php

declare(strict_types=1);

return [
    path('/', 'home'),
    path('/users/<int:id>', 'user_detail'),
    path('/register', 'auth_register'),
    path('/login', 'auth_signin'),
    path('/logout', 'auth_logout'),
    path('/dashboard', 'dashboard'),
];
