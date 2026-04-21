<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Module\System\Locale\AppLocale;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\LocaleAwareInterface;

final class LocaleSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = 'app.locale';

    public function __construct(
        private readonly LocaleAwareInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;
        $locale = AppLocale::DEFAULT;

        if (null !== $session) {
            $locale = AppLocale::normalize((string) $session->get(self::SESSION_KEY, AppLocale::DEFAULT));
        }

        $attributeLocale = $request->attributes->get('_locale');
        if (\is_string($attributeLocale) && '' !== trim($attributeLocale)) {
            $locale = AppLocale::normalize($attributeLocale);
        }

        $request->setLocale($locale);
        $this->translator->setLocale($locale);

        if (null !== $session) {
            $session->set(self::SESSION_KEY, $locale);
        }
    }
}
