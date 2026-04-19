<?php

declare(strict_types=1);

namespace App\Module\Ticket\Entity;

use App\Module\Identity\Enum\UserType;
use App\Module\Shared\Entity\TimestampableTrait;
use App\Module\Ticket\Enum\TicketIntakeFieldType;
use App\Module\Ticket\Enum\TicketRequestType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ticket_intake_fields')]
#[ORM\HasLifecycleCallbacks]
class TicketIntakeField
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: TicketRequestType::class)]
    private TicketRequestType $requestType;

    #[ORM\Column(length: 80)]
    private string $fieldKey;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(enumType: TicketIntakeFieldType::class, options: ['default' => 'text'])]
    private TicketIntakeFieldType $fieldType = TicketIntakeFieldType::TEXT;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $helpText = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $placeholder = null;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column]
    private int $sortOrder = 100;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $selectOptions = [];

    #[ORM\ManyToOne(targetEntity: TicketCategory::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TicketCategory $category = null;

    #[ORM\Column(enumType: UserType::class, nullable: true)]
    private ?UserType $customerType = null;

    #[ORM\ManyToOne(targetEntity: TicketIntakeTemplate::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?TicketIntakeTemplate $template = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $dependsOnFieldKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dependsOnFieldValue = null;

    public function __construct(TicketRequestType $requestType, string $fieldKey, string $label)
    {
        $this->requestType = $requestType;
        $this->fieldKey = self::normalizeFieldKey($fieldKey);
        $this->label = trim($label);
    }

    public static function normalizeFieldKey(string $fieldKey): string
    {
        $normalized = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower(trim($fieldKey))) ?? '';

        return trim($normalized, '_');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequestType(): TicketRequestType
    {
        return $this->requestType;
    }

    public function setRequestType(TicketRequestType $requestType): self
    {
        $this->requestType = $requestType;

        return $this;
    }

    public function getFieldKey(): string
    {
        return $this->fieldKey;
    }

    public function setFieldKey(string $fieldKey): self
    {
        $this->fieldKey = self::normalizeFieldKey($fieldKey);

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);

        return $this;
    }

    public function getFieldType(): TicketIntakeFieldType
    {
        return $this->fieldType;
    }

    public function setFieldType(TicketIntakeFieldType $fieldType): self
    {
        $this->fieldType = $fieldType;

        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): self
    {
        $this->helpText = null !== $helpText && '' !== trim($helpText) ? trim($helpText) : null;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(?string $placeholder): self
    {
        $this->placeholder = null !== $placeholder && '' !== trim($placeholder) ? trim($placeholder) : null;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setRequired(bool $required): self
    {
        $this->isRequired = $required;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = max(0, $sortOrder);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): self
    {
        $this->isActive = true;

        return $this;
    }

    public function deactivate(): self
    {
        $this->isActive = false;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSelectOptions(): array
    {
        return $this->selectOptions;
    }

    /**
     * @param list<string> $selectOptions
     */
    public function setSelectOptions(array $selectOptions): self
    {
        $normalized = [];
        foreach ($selectOptions as $option) {
            $value = trim((string) $option);
            if ('' !== $value) {
                $normalized[] = $value;
            }
        }

        $this->selectOptions = array_values(array_unique($normalized));

        return $this;
    }

    public function getCategory(): ?TicketCategory
    {
        return $this->category;
    }

    public function setCategory(?TicketCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCustomerType(): ?UserType
    {
        return $this->customerType;
    }

    public function setCustomerType(?UserType $customerType): self
    {
        $this->customerType = $customerType;

        return $this;
    }

    public function getTemplate(): ?TicketIntakeTemplate
    {
        return $this->template;
    }

    public function setTemplate(?TicketIntakeTemplate $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getEffectiveRequestType(): TicketRequestType
    {
        return $this->template?->getRequestType() ?? $this->requestType;
    }

    public function getEffectiveCategory(): ?TicketCategory
    {
        return $this->template?->getCategory() ?? $this->category;
    }

    public function getEffectiveCustomerType(): ?UserType
    {
        return $this->template?->getCustomerType() ?? $this->customerType;
    }

    public function getDependsOnFieldKey(): ?string
    {
        return $this->dependsOnFieldKey;
    }

    public function setDependsOnFieldKey(?string $dependsOnFieldKey): self
    {
        $normalized = null !== $dependsOnFieldKey ? self::normalizeFieldKey($dependsOnFieldKey) : '';
        $this->dependsOnFieldKey = '' !== $normalized ? $normalized : null;

        return $this;
    }

    public function getDependsOnFieldValue(): ?string
    {
        return $this->dependsOnFieldValue;
    }

    public function setDependsOnFieldValue(?string $dependsOnFieldValue): self
    {
        $this->dependsOnFieldValue = null !== $dependsOnFieldValue && '' !== trim($dependsOnFieldValue) ? trim($dependsOnFieldValue) : null;

        return $this;
    }
}
