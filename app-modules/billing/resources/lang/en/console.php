<?php

declare(strict_types=1);

return [
    'features_check' => [
        'description'     => 'Check that every plan version grant matches a registered feature.',
        'unknown_feature' => ':feature_key is granted by :count plan version(s) but is not registered.',
        'mixed_types'     => ':feature_key is granted with several types: :granted_types.',
        'type_changed'    => ':feature_key is granted as :granted_type but registered as :registered_type.',
        'failed'          => ':count feature(s) do not match their plan version grants.',
        'passed'          => ':count granted feature(s) match the registered features.',
    ],
];
