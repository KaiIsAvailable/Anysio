<?php

return [
    'modules' => [
        'profile menu'      => ['view package', 'boost limit', 'setting'],
        'dashboard'         => ['tab', 'lease list', 'invoice list', 'property analysis', 'unit analysis', 'room analysis'],
        'owner'             => ['tab', 'create', 'edit', 'delete', 'show'],
        'tenant'            => ['tab', 'create', 'edit', 'delete', 'show', 'view IC'],
        'property'          => ['tab', 'create', 'edit', 'delete', 'show unit'],
        'unit'              => ['create', 'edit', 'delete', 'show room'],
        'room'              => ['create', 'edit', 'delete', 'show'],
        'leases'            => ['tab', 'agreement template', 'lease controller', 'upload stamping', 'view agreement', 'cancel lease', 'show', 'auto generate invoice', 'add manual invoice', 'record payment', 'void', 'view invoice', 'view receipt'],
        'document template' => ['create', 'preview', 'print', 'edit', 'set active document'],
        'invoice'           => ['tab', 'record payment', 'void', 'view invoice', 'view receipt'],
        'staff'             => ['tab', 'create', 'edit', 'delete', 'show'],
        'settings'          => ['payment tab', 'payment edit', 'lease tab', 'recurring invoice setting', 'edit recurring invoice', 'pending renewal setting', 'edit pending renewal', 'lease configuration setting', 'edit lease configuration', 'user role tab', 'create new role', 'edit user role', 'delete user role']
    ],
];