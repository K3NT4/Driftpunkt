<?php

declare(strict_types=1);

namespace App\Module\System\EventSubscriber;

use App\Module\System\Service\OperationalTaskRunner;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class OperationalTaskSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OperationalTaskRunner $operationalTaskRunner,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', -255],
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ('test' === $this->environment || !$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/_wdt') || str_starts_with($path, '/_profiler')) {
            return;
        }

        try {
            $this->operationalTaskRunner->queueDueRunner();
        } catch (\Throwable) {
            // Scheduler fallback must never break the page response.
        }
    }
}
