<?php

declare(strict_types=1);

return [
    path('/', 'home'),
    path('/users/<int:id>', 'user_detail'),
];
