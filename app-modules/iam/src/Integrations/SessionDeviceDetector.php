<?php

declare(strict_types=1);

namespace Lahatre\Iam\Integrations;

use DeviceDetector\Cache\PSR16Bridge;
use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Cache;

final class SessionDeviceDetector
{
    /** @return array{name: string, type: string|null, os: string|null}|null */
    public function detect(?string $userAgent): ?array
    {
        if (!$userAgent) {
            return null;
        }

        $result = Cache::remember('iam:session-device:'.DeviceDetector::VERSION.':'.hash('sha256', $userAgent), 86400, function () use ($userAgent): array {
            $detector = new DeviceDetector($userAgent);
            $detector->setCache(new PSR16Bridge(Cache::store()));
            $detector->parse();
            $os = $detector->getOs('name');
            $type = $detector->getDeviceName();
            if ($detector->isBot() || (($os === DeviceDetector::UNKNOWN || !is_string($os)) && !$type)) {
                return ['device' => null];
            }

            $os = is_string($os) && $os !== '' && $os !== DeviceDetector::UNKNOWN ? $os : null;
            $name = match (true) {
                $detector->isSmartphone(), $detector->isFeaturePhone() => __('iam::messages.session_device.phone'),
                $detector->isTablet()                                  => __('iam::messages.session_device.tablet'),
                $os === 'Mac'                                          => 'Mac',
                default                                                => __('iam::messages.session_device.computer'),
            };
            $model = $detector->getModel();
            if ($model !== '') {
                $name = trim($detector->getBrandName().' '.$model);
            }

            return ['device' => ['name' => $name, 'type' => $type ?: null, 'os' => $os === 'Mac' ? 'macOS' : $os]];
        });

        return $result['device'];
    }
}
