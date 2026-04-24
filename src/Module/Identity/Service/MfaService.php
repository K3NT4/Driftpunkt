<?php

declare(strict_types=1);

namespace App\Module\Identity\Service;

use App\Module\Identity\Entity\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

final class MfaService
{
    private const QR_CODE_SIZE = 280;
    private ?Google2FA $google2FA = null;

    public function ensureSecret(User $user): string
    {
        $secret = $user->getMfaSecret();
        if (null !== $secret && '' !== $secret) {
            return $secret;
        }

        $secret = $this->google2FA()->generateSecretKey();
        $user->setMfaSecret($secret);

        return $secret;
    }

    public function getOtpAuthUri(User $user, string $issuer): string
    {
        return $this->google2FA()->getQRCodeUrl(
            $issuer,
            $user->getEmail(),
            $this->ensureSecret($user),
        );
    }

    public function getQrCodeDataUri(User $user, string $issuer): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(self::QR_CODE_SIZE),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($this->getOtpAuthUri($user, $issuer));

        return sprintf('data:image/svg+xml;base64,%s', base64_encode($svg));
    }

    public function getFormattedManualEntryCode(User $user): string
    {
        $secret = $this->ensureSecret($user);

        return trim(chunk_split($secret, 4, ' '));
    }

    public function verifyCode(User $user, string $code): bool
    {
        $secret = $user->getMfaSecret();
        if (null === $secret || '' === $secret) {
            return false;
        }

        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';
        if ('' === $normalizedCode) {
            return false;
        }

        return $this->google2FA()->verifyKey($secret, $normalizedCode, 1);
    }

    private function google2FA(): Google2FA
    {
        return $this->google2FA ??= new Google2FA();
    }
}
