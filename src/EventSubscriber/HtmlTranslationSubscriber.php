<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Module\System\Locale\AppLocale;
use App\Twig\AppRuntime;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class HtmlTranslationSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<string>
     */
    private const TRANSLATABLE_ATTRIBUTES = ['placeholder', 'title', 'aria-label', 'alt'];

    public function __construct(
        private readonly AppRuntime $appRuntime,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $locale = AppLocale::normalize($request->getLocale());

        if (AppLocale::DEFAULT === $locale || !$this->isTranslatableHtmlResponse($response)) {
            return;
        }

        $html = (string) $response->getContent();
        if ('' === trim($html)) {
            return;
        }

        $translatedHtml = $this->translateHtml($html, $locale);
        if (null === $translatedHtml) {
            return;
        }

        $response->setContent($translatedHtml);
    }

    private function isTranslatableHtmlResponse(Response $response): bool
    {
        if ($response->isRedirection() || $response->isEmpty()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html');
    }

    private function translateHtml(string $html, string $locale): ?string
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');

        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, \LIBXML_NOERROR | \LIBXML_NOWARNING);
            if (false === $loaded || !$document->documentElement instanceof \DOMElement) {
                return null;
            }

            $this->translateNode($document->documentElement, $locale);

            $output = $document->saveHTML();
            if (!\is_string($output) || '' === $output) {
                return null;
            }

            return preg_replace('/^<\\?xml.+?\\?>/i', '', $output) ?? $output;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function translateNode(\DOMNode $node, string $locale): void
    {
        if ($node instanceof \DOMElement) {
            if (\in_array($node->tagName, ['script', 'style', 'code', 'pre', 'textarea'], true)) {
                return;
            }

            foreach (self::TRANSLATABLE_ATTRIBUTES as $attribute) {
                if (!$node->hasAttribute($attribute)) {
                    continue;
                }

                $translated = $this->translateText($node->getAttribute($attribute), $locale);
                if (null !== $translated) {
                    $node->setAttribute($attribute, $translated);
                }
            }

            if ('input' === $node->tagName) {
                $type = mb_strtolower($node->getAttribute('type'));
                if (\in_array($type, ['submit', 'button', 'reset'], true) && $node->hasAttribute('value')) {
                    $translated = $this->translateText($node->getAttribute('value'), $locale);
                    if (null !== $translated) {
                        $node->setAttribute('value', $translated);
                    }
                }
            }
        }

        if ($node instanceof \DOMText) {
            $translated = $this->translateText($node->nodeValue, $locale);
            if (null !== $translated) {
                $node->nodeValue = $translated;
            }

            return;
        }

        foreach (iterator_to_array($node->childNodes) as $childNode) {
            $this->translateNode($childNode, $locale);
        }
    }

    private function translateText(?string $text, string $locale): ?string
    {
        if (null === $text) {
            return null;
        }

        if (preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $matches) !== 1) {
            return null;
        }

        $content = trim($matches[2]);
        if ('' === $content) {
            return null;
        }

        $translated = $this->appRuntime->translate($content, locale: $locale);
        if ($translated === $content) {
            return null;
        }

        return $matches[1].$translated.$matches[3];
    }
}
