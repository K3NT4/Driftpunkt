<?php

declare(strict_types=1);

namespace App\Module\System\Entity;

use App\Module\Shared\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'system_settings')]
#[ORM\HasLifecycleCallbacks]
class SystemSetting
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(length: 120)]
    private string $settingKey;

    #[ORM\Column(type: 'text')]
    private string $settingValue;

    public function __construct(string $settingKey, string $settingValue)
    {
        $this->settingKey = trim($settingKey);
        $this->settingValue = trim($settingValue);
    }

    public function getSettingKey(): string
    {
        return $this->settingKey;
    }

    public function getSettingValue(): string
    {
        return $this->settingValue;
    }

    public function setSettingValue(string $settingValue): self
    {
        $this->settingValue = trim($settingValue);

        return $this;
    }
}
