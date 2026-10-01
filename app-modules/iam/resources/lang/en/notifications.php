<?php

declare(strict_types=1);

return [
    'email_login' => [
        'subject' => 'Your sign-in code',
        'body'    => 'Your sign-in code is :code.',
        'expiry'  => 'This code expires in 10 minutes. If you did not request it, you can ignore this email.',
    ],
    'invitation' => [
        'subject' => 'Organization invitation',
        'body'    => 'You have been invited to join :organization. Use this link to accept the invitation.',
        'action'  => 'Accept invitation',
    ],
    'organization_registration' => [
        'subject' => 'Create your organization',
        'body'    => 'Use this link to finish creating your organization.',
        'action'  => 'Continue registration',
    ],
];
