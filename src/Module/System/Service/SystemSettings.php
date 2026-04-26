<?php

declare(strict_types=1);

namespace App\Module\System\Service;

use App\Module\System\Entity\SystemSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SystemSettings
{
    public const SLA_FIRST_RESPONSE_WARNING_HOURS = 'sla.first_response_warning_hours';
    public const SLA_RESOLUTION_WARNING_HOURS = 'sla.resolution_warning_hours';
    public const FEATURE_TICKET_TEMPLATE_PLAYBOOK_ENABLED = 'feature.ticket_template_playbook_enabled';
    public const FEATURE_TICKET_TEMPLATE_CHECKLIST_ENABLED = 'feature.ticket_template_checklist_enabled';
    public const FEATURE_TICKET_TEMPLATE_CHECKLIST_PROGRESS_ENABLED = 'feature.ticket_template_checklist_progress_enabled';
    public const FEATURE_TICKET_TEMPLATE_CHECKLIST_CUSTOMER_VISIBLE = 'feature.ticket_template_checklist_customer_visible';
    public const FEATURE_REMOTE_SUPPORT_ANYDESK_ENABLED = 'feature.remote_support_anydesk_enabled';
    public const FEATURE_REMOTE_SUPPORT_TEAMVIEWER_ENABLED = 'feature.remote_support_teamviewer_enabled';
    public const FEATURE_COMPANY_HIERARCHY_PARENT_CAN_SEE_CHILD_SHARED_TICKETS = 'feature.company_hierarchy_parent_can_see_child_shared_tickets';
    public const FEATURE_COMPANY_HIERARCHY_CHILD_CAN_SEE_PARENT_SHARED_TICKETS = 'feature.company_hierarchy_child_can_see_parent_shared_tickets';
    public const FEATURE_CUSTOMER_REPORTS_ENABLED = 'feature.customer_reports_enabled';
    public const FEATURE_TICKET_ATTACHMENTS_ENABLED = 'feature.ticket_attachments_enabled';
    public const FEATURE_TICKET_ATTACHMENTS_EXTERNAL_ENABLED = 'feature.ticket_attachments_external_enabled';
    public const TICKET_ATTACHMENTS_MAX_UPLOAD_MB = 'ticket_attachments.max_upload_mb';
    public const TICKET_ATTACHMENTS_STORAGE_PATH = 'ticket_attachments.storage_path';
    public const TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS = 'ticket_attachments.allowed_extensions';
    public const TICKET_ATTACHMENTS_EXTERNAL_PROVIDER_LABEL = 'ticket_attachments.external_provider_label';
    public const TICKET_ATTACHMENTS_EXTERNAL_INSTRUCTIONS = 'ticket_attachments.external_instructions';
    public const FEATURE_TICKET_ATTACHMENTS_ZIP_ARCHIVING_ENABLED = 'feature.ticket_attachments_zip_archiving_enabled';
    public const TICKET_ATTACHMENTS_ZIP_ARCHIVE_AFTER_DAYS = 'ticket_attachments.zip_archive_after_days';
    public const FEATURE_PUBLIC_TICKET_FORM_ENABLED = 'feature.public_ticket_form_enabled';
    public const FEATURE_CUSTOMER_SELF_REGISTRATION_ENABLED = 'feature.customer_self_registration_enabled';
    public const CUSTOMER_LOGIN_SMART_TIPS = 'customer_login.smart_tips';
    public const CUSTOMER_LOGIN_FAQ = 'customer_login.faq';
    public const FEATURE_MFA_CUSTOMER_ENABLED = 'feature.mfa_customer_enabled';
    public const FEATURE_MFA_TECHNICIAN_ENABLED = 'feature.mfa_technician_enabled';
    public const FEATURE_MFA_ADMIN_ENABLED = 'feature.mfa_admin_enabled';
    public const FEATURE_KNOWLEDGE_BASE_PUBLIC_ENABLED = 'feature.knowledge_base_public_enabled';
    public const FEATURE_KNOWLEDGE_BASE_CUSTOMER_ENABLED = 'feature.knowledge_base_customer_enabled';
    public const FEATURE_KNOWLEDGE_BASE_PUBLIC_SMART_TIPS_ENABLED = 'feature.knowledge_base_public_smart_tips_enabled';
    public const FEATURE_KNOWLEDGE_BASE_PUBLIC_FAQ_ENABLED = 'feature.knowledge_base_public_faq_enabled';
    public const FEATURE_KNOWLEDGE_BASE_CUSTOMER_SMART_TIPS_ENABLED = 'feature.knowledge_base_customer_smart_tips_enabled';
    public const FEATURE_KNOWLEDGE_BASE_CUSTOMER_FAQ_ENABLED = 'feature.knowledge_base_customer_faq_enabled';
    public const FEATURE_KNOWLEDGE_BASE_SMART_TIPS_ENABLED = 'feature.knowledge_base_smart_tips_enabled';
    public const FEATURE_KNOWLEDGE_BASE_FAQ_ENABLED = 'feature.knowledge_base_faq_enabled';
    public const FEATURE_KNOWLEDGE_BASE_PUBLIC_TECHNICIAN_CONTRIBUTIONS_ENABLED = 'feature.knowledge_base_public_technician_contributions_enabled';
    public const FEATURE_KNOWLEDGE_BASE_CUSTOMER_TECHNICIAN_CONTRIBUTIONS_ENABLED = 'feature.knowledge_base_customer_technician_contributions_enabled';
    public const FEATURE_NEWS_TECHNICIAN_CONTRIBUTIONS_ENABLED = 'feature.news_technician_contributions_enabled';
    public const HOME_SUPPORT_WIDGET_TITLE = 'home.support_widget_title';
    public const HOME_SUPPORT_WIDGET_INTRO = 'home.support_widget_intro';
    public const HOME_SUPPORT_WIDGET_LINKS = 'home.support_widget_links';
    public const HOME_STATUS_SECTION_TITLE = 'home.status_section_title';
    public const HOME_STATUS_SECTION_INTRO = 'home.status_section_intro';
    public const HOME_STATUS_SECTION_MAX_ITEMS = 'home.status_section_max_items';
    public const PUBLIC_STATUS_ITEMS = 'public.status_items';
    public const STATUS_MONITORS = 'status.monitors';
    public const STATUS_PAGE_SHOW_SYSTEM_CHECKED_AT = 'status.page_show_system_checked_at';
    public const STATUS_PAGE_SHOW_SYSTEM_SOURCE = 'status.page_show_system_source';
    public const STATUS_PAGE_SHOW_RECENT_UPDATES = 'status.page_show_recent_updates';
    public const STATUS_PAGE_RECENT_UPDATES_TITLE = 'status.page_recent_updates_title';
    public const STATUS_PAGE_RECENT_UPDATES_INTRO = 'status.page_recent_updates_intro';
    public const STATUS_PAGE_RECENT_UPDATES_MAX_ITEMS = 'status.page_recent_updates_max_items';
    public const STATUS_PAGE_SHOW_IMPACT = 'status.page_show_impact';
    public const STATUS_PAGE_IMPACT_TITLE = 'status.page_impact_title';
    public const STATUS_PAGE_IMPACT_INTRO = 'status.page_impact_intro';
    public const STATUS_PAGE_IMPACT_ITEMS = 'status.page_impact_items';
    public const STATUS_PAGE_SHOW_HISTORY = 'status.page_show_history';
    public const STATUS_PAGE_SHOW_SUBSCRIBE_BOX = 'status.page_show_subscribe_box';
    public const STATUS_PAGE_SUBSCRIBE_TITLE = 'status.page_subscribe_title';
    public const STATUS_PAGE_SUBSCRIBE_TEXT = 'status.page_subscribe_text';
    public const STATUS_PAGE_SUBSCRIBE_LINK_LABEL = 'status.page_subscribe_link_label';
    public const STATUS_PAGE_SUBSCRIBE_LINK_URL = 'status.page_subscribe_link_url';
    public const MAINTENANCE_NOTICE_LOOKAHEAD_DAYS = 'maintenance.notice_lookahead_days';
    public const CONTACT_PAGE_HERO_PILL = 'contact.hero_pill';
    public const CONTACT_PAGE_TITLE = 'contact.title';
    public const CONTACT_PAGE_SUBTITLE = 'contact.subtitle';
    public const CONTACT_PAGE_EMAIL = 'contact.email';
    public const CONTACT_PAGE_PHONE = 'contact.phone';
    public const CONTACT_PAGE_HOURS = 'contact.hours';
    public const CONTACT_PAGE_PRIMARY_CTA_LABEL = 'contact.primary_cta_label';
    public const CONTACT_PAGE_PRIMARY_CTA_URL = 'contact.primary_cta_url';
    public const CONTACT_PAGE_SECONDARY_CTA_LABEL = 'contact.secondary_cta_label';
    public const CONTACT_PAGE_SECONDARY_CTA_URL = 'contact.secondary_cta_url';
    public const CONTACT_PAGE_STATUS_LINES = 'contact.status_lines';
    public const CONTACT_PAGE_CHANNEL_CARDS = 'contact.channel_cards';
    public const CONTACT_PAGE_QUICK_HELP_TITLE = 'contact.quick_help_title';
    public const CONTACT_PAGE_QUICK_HELP_INTRO = 'contact.quick_help_intro';
    public const CONTACT_PAGE_QUICK_HELP_STEPS = 'contact.quick_help_steps';
    public const CONTACT_PAGE_QUICK_HELP_NOTE = 'contact.quick_help_note';
    public const CONTACT_PAGE_PRIORITY_TITLE = 'contact.priority_title';
    public const CONTACT_PAGE_PRIORITY_INTRO = 'contact.priority_intro';
    public const CONTACT_PAGE_PRIORITY_LINK_LABELS = 'contact.priority_link_labels';
    public const CONTACT_PAGE_WHEN_TITLE = 'contact.when_title';
    public const CONTACT_PAGE_WHEN_ITEMS = 'contact.when_items';
    public const CONTACT_PAGE_EXTRA_HELP_TITLE = 'contact.extra_help_title';
    public const CONTACT_PAGE_EXTRA_HELP_INTRO = 'contact.extra_help_intro';
    public const I18N_AVAILABLE_LOCALES = 'i18n.available_locales';
    public const I18N_TRANSLATION_OVERRIDES = 'i18n.translation_overrides';
    public const SITE_BRAND_NAME = 'site.brand_name';
    public const SITE_BRAND_LOGO_PATH = 'site.brand_logo_path';
    public const SITE_FOOTER_TEXT = 'site.footer_text';
    public const PRIVACY_POLICY_TITLE = 'privacy_policy.title';
    public const PRIVACY_POLICY_INTRO = 'privacy_policy.intro';
    public const PRIVACY_POLICY_BODY = 'privacy_policy.body';
    public const PRIVACY_POLICY_EFFECTIVE_DATE = 'privacy_policy.effective_date';
    public const PRIVACY_POLICY_CONTACT_EMAIL = 'privacy_policy.contact_email';
    public const FEATURE_PRIVACY_POLICY_EXTERNAL_ENABLED = 'feature.privacy_policy_external_enabled';
    public const PRIVACY_POLICY_EXTERNAL_URL = 'privacy_policy.external_url';
    public const TERMS_PAGE_TITLE = 'terms_page.title';
    public const TERMS_PAGE_INTRO = 'terms_page.intro';
    public const TERMS_PAGE_BODY = 'terms_page.body';
    public const TERMS_PAGE_EFFECTIVE_DATE = 'terms_page.effective_date';
    public const TERMS_PAGE_CONTACT_EMAIL = 'terms_page.contact_email';
    public const FEATURE_TERMS_PAGE_EXTERNAL_ENABLED = 'feature.terms_page_external_enabled';
    public const TERMS_PAGE_EXTERNAL_URL = 'terms_page.external_url';
    public const COOKIE_POLICY_TITLE = 'cookie_policy.title';
    public const COOKIE_POLICY_INTRO = 'cookie_policy.intro';
    public const COOKIE_POLICY_BODY = 'cookie_policy.body';
    public const COOKIE_POLICY_EFFECTIVE_DATE = 'cookie_policy.effective_date';
    public const COOKIE_POLICY_CONTACT_EMAIL = 'cookie_policy.contact_email';
    public const FEATURE_COOKIE_POLICY_EXTERNAL_ENABLED = 'feature.cookie_policy_external_enabled';
    public const COOKIE_POLICY_EXTERNAL_URL = 'cookie_policy.external_url';
    public const UPDATE_RELEASE_PENDING_CONFIRMATION = 'update_release.pending_confirmation';
    public const UPDATE_RELEASE_PACKAGE_NAME = 'update_release.package_name';
    public const UPDATE_RELEASE_PACKAGE_VERSION = 'update_release.package_version';
    public const UPDATE_RELEASE_APPLIED_AT = 'update_release.applied_at';
    public const UPDATE_RELEASE_CONFIRMED_AT = 'update_release.confirmed_at';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getInt(string $key, int $default): int
    {
        $setting = $this->entityManager->getRepository(SystemSetting::class)->find($key);
        if (!$setting instanceof SystemSetting) {
            return $default;
        }

        return max(1, (int) $setting->getSettingValue());
    }

    public function getNonNegativeInt(string $key, int $default): int
    {
        $setting = $this->entityManager->getRepository(SystemSetting::class)->find($key);
        if (!$setting instanceof SystemSetting) {
            return max(0, $default);
        }

        return max(0, (int) $setting->getSettingValue());
    }

    public function setInt(string $key, int $value): void
    {
        $setting = $this->entityManager->getRepository(SystemSetting::class)->find($key);
        if (!$setting instanceof SystemSetting) {
            $setting = new SystemSetting($key, (string) $value);
            $this->entityManager->persist($setting);
        } else {
            $setting->setSettingValue((string) $value);
        }

        $this->entityManager->flush();
    }

    public function getBool(string $key, bool $default): bool
    {
        $setting = $this->entityManager->getRepository(SystemSetting::class)->find($key);
        if (!$setting instanceof SystemSetting) {
            return $default;
        }

        return \in_array(mb_strtolower(trim($setting->getSettingValue())), ['1', 'true', 'yes', 'on'], true);
    }

    public function setBool(string $key, bool $value): void
    {
        $setting = $this->entityManager->getRepository(SystemSetting::class)->find($key);
        if (!$setting instanceof SystemSetting) {
            $setting = new SystemSetting($key, $value ? '1' : '0');
            $this->entityManager->persist($setting);
        } else {
            $setting->setSettingValue($value ? '1' : '0');
        }

        $this->entityManager->flush();
    }

    public function getString(string $key, string $default): string
    {
        $setting = $this->entityManager->getRepository(SystemSetting::class)->find($key);
        if (!$setting instanceof SystemSetting) {
            return $default;
        }

        return trim($setting->getSettingValue());
    }

    public function setString(string $key, string $value): void
    {
        $setting = $this->entityManager->getRepository(SystemSetting::class)->find($key);
        if (!$setting instanceof SystemSetting) {
            $setting = new SystemSetting($key, $value);
            $this->entityManager->persist($setting);
        } else {
            $setting->setSettingValue($value);
        }

        $this->entityManager->flush();
    }

    /**
     * @return array{
     *     pendingConfirmation: bool,
     *     packageName: string,
     *     packageVersion: string,
     *     appliedAt: ?\DateTimeImmutable,
     *     confirmedAt: ?\DateTimeImmutable
     * }
     */
    public function getUpdateReleaseState(): array
    {
        return [
            'pendingConfirmation' => $this->getBool(self::UPDATE_RELEASE_PENDING_CONFIRMATION, false),
            'packageName' => trim($this->getString(self::UPDATE_RELEASE_PACKAGE_NAME, '')),
            'packageVersion' => trim($this->getString(self::UPDATE_RELEASE_PACKAGE_VERSION, '')),
            'appliedAt' => $this->parseStoredDateTime($this->getString(self::UPDATE_RELEASE_APPLIED_AT, '')),
            'confirmedAt' => $this->parseStoredDateTime($this->getString(self::UPDATE_RELEASE_CONFIRMED_AT, '')),
        ];
    }

    public function markUpdateReleasePendingConfirmation(string $packageName, string $packageVersion, ?\DateTimeImmutable $appliedAt = null): void
    {
        $this->setBool(self::UPDATE_RELEASE_PENDING_CONFIRMATION, true);
        $this->setString(self::UPDATE_RELEASE_PACKAGE_NAME, trim($packageName));
        $this->setString(self::UPDATE_RELEASE_PACKAGE_VERSION, trim($packageVersion));
        $this->setString(self::UPDATE_RELEASE_APPLIED_AT, ($appliedAt ?? new \DateTimeImmutable())->format(DATE_ATOM));
        $this->setString(self::UPDATE_RELEASE_CONFIRMED_AT, '');
    }

    public function confirmUpdateReleaseHealthy(): void
    {
        $this->setBool(self::UPDATE_RELEASE_PENDING_CONFIRMATION, false);
        $this->setString(self::UPDATE_RELEASE_CONFIRMED_AT, (new \DateTimeImmutable())->format(DATE_ATOM));
    }

    private function parseStoredDateTime(string $value): ?\DateTimeImmutable
    {
        $normalized = trim($value);
        if ('' === $normalized) {
            return null;
        }

        try {
            return new \DateTimeImmutable($normalized);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function getTranslationLocales(): array
    {
        $defaultLocales = [
            'sv' => 'Svenska',
            'en' => 'English',
        ];

        $stored = json_decode($this->getString(self::I18N_AVAILABLE_LOCALES, ''), true);
        if (!\is_array($stored)) {
            $stored = [];
        }

        $locales = [];
        foreach ($defaultLocales as $code => $name) {
            $locales[$code] = $name;
        }

        foreach ($stored as $code => $name) {
            $normalizedCode = \is_string($code) ? trim(mb_strtolower(str_replace('_', '-', $code))) : '';
            $normalizedName = \is_string($name) ? trim($name) : '';

            if ('' === $normalizedCode || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $normalizedCode) !== 1) {
                continue;
            }

            $locales[$normalizedCode] = '' !== $normalizedName ? $normalizedName : mb_strtoupper($normalizedCode);
        }

        $entries = [];
        foreach ($locales as $code => $name) {
            $entries[] = [
                'code' => $code,
                'name' => $name,
            ];
        }

        usort(
            $entries,
            static function (array $left, array $right): int {
                if ('sv' === $left['code']) {
                    return -1;
                }

                if ('sv' === $right['code']) {
                    return 1;
                }

                if ('en' === $left['code']) {
                    return -1;
                }

                if ('en' === $right['code']) {
                    return 1;
                }

                return strcmp($left['code'], $right['code']);
            },
        );

        return $entries;
    }

    /**
     * @param list<array{code: string, name: string}> $locales
     */
    public function setTranslationLocales(array $locales): void
    {
        $normalized = [];

        foreach ($locales as $locale) {
            $code = trim(mb_strtolower(str_replace('_', '-', (string) ($locale['code'] ?? ''))));
            $name = trim((string) ($locale['name'] ?? ''));

            if ('' === $code || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $code) !== 1) {
                continue;
            }

            $normalized[$code] = '' !== $name ? $name : mb_strtoupper($code);
        }

        $normalized['sv'] = $normalized['sv'] ?? 'Svenska';
        $normalized['en'] = $normalized['en'] ?? 'English';

        ksort($normalized);
        $this->setString(self::I18N_AVAILABLE_LOCALES, json_encode($normalized, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT) ?: '{}');
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getTranslationOverrides(): array
    {
        $decoded = json_decode($this->getString(self::I18N_TRANSLATION_OVERRIDES, ''), true);
        if (!\is_array($decoded)) {
            return [];
        }

        $catalogues = [];
        foreach ($decoded as $locale => $messages) {
            $normalizedLocale = \is_string($locale) ? trim(mb_strtolower(str_replace('_', '-', $locale))) : '';
            if ('' === $normalizedLocale || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $normalizedLocale) !== 1 || !\is_array($messages)) {
                continue;
            }

            $catalogues[$normalizedLocale] = [];
            foreach ($messages as $key => $message) {
                if (!\is_string($key) || !\is_scalar($message)) {
                    continue;
                }

                $normalizedKey = trim($key);
                $normalizedMessage = trim((string) $message);
                if ('' === $normalizedKey || '' === $normalizedMessage) {
                    continue;
                }

                $catalogues[$normalizedLocale][$normalizedKey] = $normalizedMessage;
            }
        }

        return $catalogues;
    }

    /**
     * @return array<string, string>
     */
    public function getTranslationOverridesForLocale(string $locale): array
    {
        $locale = trim(mb_strtolower(str_replace('_', '-', $locale)));

        return $this->getTranslationOverrides()[$locale] ?? [];
    }

    /**
     * @param array<string, string> $messages
     */
    public function setTranslationOverridesForLocale(string $locale, array $messages): void
    {
        $locale = trim(mb_strtolower(str_replace('_', '-', $locale)));
        if ('' === $locale || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale) !== 1) {
            return;
        }

        $catalogues = $this->getTranslationOverrides();
        $catalogues[$locale] = [];

        foreach ($messages as $key => $message) {
            $normalizedKey = trim((string) $key);
            $normalizedMessage = trim((string) $message);

            if ('' === $normalizedKey || '' === $normalizedMessage) {
                continue;
            }

            $catalogues[$locale][$normalizedKey] = $normalizedMessage;
        }

        if ([] === $catalogues[$locale]) {
            unset($catalogues[$locale]);
        }

        ksort($catalogues);
        foreach ($catalogues as &$catalogue) {
            ksort($catalogue);
        }
        unset($catalogue);

        $this->setString(self::I18N_TRANSLATION_OVERRIDES, json_encode($catalogues, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT) ?: '{}');
    }

    /**
     * @return array{firstResponseWarningHours: int, resolutionWarningHours: int}
     */
    public function getSlaWarningSettings(): array
    {
        return [
            'firstResponseWarningHours' => $this->getInt(self::SLA_FIRST_RESPONSE_WARNING_HOURS, 2),
            'resolutionWarningHours' => $this->getInt(self::SLA_RESOLUTION_WARNING_HOURS, 8),
        ];
    }

    /**
     * @return array{playbookEnabled: bool, checklistEnabled: bool, checklistProgressEnabled: bool, checklistCustomerVisible: bool}
     */
    public function getTicketTemplateGuidanceSettings(): array
    {
        return [
            'playbookEnabled' => $this->getBool(self::FEATURE_TICKET_TEMPLATE_PLAYBOOK_ENABLED, false),
            'checklistEnabled' => $this->getBool(self::FEATURE_TICKET_TEMPLATE_CHECKLIST_ENABLED, false),
            'checklistProgressEnabled' => $this->getBool(self::FEATURE_TICKET_TEMPLATE_CHECKLIST_PROGRESS_ENABLED, false),
            'checklistCustomerVisible' => $this->getBool(self::FEATURE_TICKET_TEMPLATE_CHECKLIST_CUSTOMER_VISIBLE, false),
        ];
    }

    /**
     * @return array{anydeskEnabled: bool, teamviewerEnabled: bool}
     */
    public function getRemoteSupportSettings(): array
    {
        return [
            'anydeskEnabled' => $this->getBool(self::FEATURE_REMOTE_SUPPORT_ANYDESK_ENABLED, false),
            'teamviewerEnabled' => $this->getBool(self::FEATURE_REMOTE_SUPPORT_TEAMVIEWER_ENABLED, false),
        ];
    }

    /**
     * @return array{
     *     parentCanSeeChildSharedTickets: bool,
     *     childCanSeeParentSharedTickets: bool
     * }
     */
    public function getCompanyHierarchyVisibilitySettings(): array
    {
        return [
            'parentCanSeeChildSharedTickets' => $this->getBool(self::FEATURE_COMPANY_HIERARCHY_PARENT_CAN_SEE_CHILD_SHARED_TICKETS, true),
            'childCanSeeParentSharedTickets' => $this->getBool(self::FEATURE_COMPANY_HIERARCHY_CHILD_CAN_SEE_PARENT_SHARED_TICKETS, false),
        ];
    }

    /**
     * @return array{customerEnabled: bool}
     */
    public function getReportSettings(): array
    {
        return [
            'customerEnabled' => $this->getBool(self::FEATURE_CUSTOMER_REPORTS_ENABLED, false),
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     maxUploadMb: int,
     *     storagePath: string,
     *     allowedExtensions: list<string>,
     *     externalUploadsEnabled: bool,
     *     externalProviderLabel: string,
     *     externalInstructions: string,
     *     zipArchivingEnabled: bool,
     *     zipArchiveAfterDays: int
     * }
     */
    public function getTicketAttachmentSettings(): array
    {
        return [
            'enabled' => $this->getBool(self::FEATURE_TICKET_ATTACHMENTS_ENABLED, false),
            'maxUploadMb' => $this->getInt(self::TICKET_ATTACHMENTS_MAX_UPLOAD_MB, 10),
            'storagePath' => $this->getString(self::TICKET_ATTACHMENTS_STORAGE_PATH, 'var/ticket_attachments'),
            'allowedExtensions' => $this->normalizeExtensionList($this->getString(
                self::TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS,
                'png,jpg,jpeg,pdf,txt,log,doc,docx,xls,xlsx,csv',
            )),
            'externalUploadsEnabled' => $this->getBool(self::FEATURE_TICKET_ATTACHMENTS_EXTERNAL_ENABLED, false),
            'externalProviderLabel' => $this->getString(self::TICKET_ATTACHMENTS_EXTERNAL_PROVIDER_LABEL, 'OneDrive, Google Drive eller liknande'),
            'externalInstructions' => $this->getString(
                self::TICKET_ATTACHMENTS_EXTERNAL_INSTRUCTIONS,
                'För stora filer: ladda upp filen i er delningstjänst och klistra in delningslänken i ärendet.',
            ),
            'zipArchivingEnabled' => $this->getBool(self::FEATURE_TICKET_ATTACHMENTS_ZIP_ARCHIVING_ENABLED, false),
            'zipArchiveAfterDays' => $this->getNonNegativeInt(self::TICKET_ATTACHMENTS_ZIP_ARCHIVE_AFTER_DAYS, 0),
        ];
    }

    /**
     * @return array{enabled: bool}
     */
    public function getPublicTicketFormSettings(): array
    {
        return [
            'enabled' => $this->getBool(self::FEATURE_PUBLIC_TICKET_FORM_ENABLED, false),
        ];
    }

    /**
     * @return array{
     *     tips: list<string>,
     *     faq: list<array{question: string, answer: string}>,
     *     faqLines: list<string>,
     *     createAccountEnabled: bool
     * }
     */
    public function getCustomerLoginSettings(): array
    {
        $tips = $this->normalizeLineList($this->getString(
            self::CUSTOMER_LOGIN_SMART_TIPS,
            "Skriv tydligt i din felbeskrivning för snabbare hjälp.\nBifoga gärna skärmbilder för att visa problemet.\nLogga in för att enkelt följa dina ärenden.",
        ));

        $faqLines = $this->normalizeLineList($this->getString(
            self::CUSTOMER_LOGIN_FAQ,
            "Jag kan inte logga in, vad gör jag? | Kontrollera att du använder rätt e-postadress och prova att återställa ditt lösenord om problemet kvarstår.\nHur hittar jag mina fakturauppgifter? | Efter inloggning hittar du dina uppgifter under din profil eller via supporten om något saknas.\nVarför fungerar inte e-posten? | Kontrollera skräpposten och säkerställ att din e-postadress är korrekt registrerad på kontot.\nHur felsöker jag en VPN-anslutning? | Starta om klienten, kontrollera internetanslutningen och ange gärna felmeddelandet i ditt ärende.",
        ));
        $faq = $this->normalizeFaqEntries($faqLines);

        return [
            'tips' => $tips,
            'faq' => $faq,
            'faqLines' => $faqLines,
            'createAccountEnabled' => $this->getBool(self::FEATURE_CUSTOMER_SELF_REGISTRATION_ENABLED, false),
        ];
    }

    /**
     * @return array{
     *     customerEnabled: bool,
     *     technicianEnabled: bool,
     *     adminEnabled: bool
     * }
     */
    public function getMfaSettings(): array
    {
        return [
            'customerEnabled' => $this->getBool(self::FEATURE_MFA_CUSTOMER_ENABLED, false),
            'technicianEnabled' => $this->getBool(self::FEATURE_MFA_TECHNICIAN_ENABLED, false),
            'adminEnabled' => $this->getBool(self::FEATURE_MFA_ADMIN_ENABLED, true),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeLineList(string $value): array
    {
        $lines = preg_split('/\R+/', trim($value)) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => '' !== $line));
    }

    /**
     * @param list<string> $lines
     * @return list<array{question: string, answer: string}>
     */
    private function normalizeFaqEntries(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $question = $parts[0] ?? '';
            $answer = $parts[1] ?? 'Svar kommer snart.';

            if ('' === $question) {
                continue;
            }

            $items[] = [
                'question' => $question,
                'answer' => '' !== $answer ? $answer : 'Svar kommer snart.',
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function normalizeExtensionList(string $value): array
    {
        $parts = preg_split('/[\s,;]+/', trim($value)) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            $extension = ltrim(mb_strtolower(trim($part)), '.');
            if ('' === $extension || !preg_match('/^[a-z0-9]+$/', $extension)) {
                continue;
            }

            $normalized[$extension] = $extension;
        }

        return array_values($normalized);
    }

    /**
     * @return array{
     *     publicEnabled: bool,
     *     customerEnabled: bool,
     *     publicSmartTipsEnabled: bool,
     *     publicFaqEnabled: bool,
     *     customerSmartTipsEnabled: bool,
     *     customerFaqEnabled: bool,
     *     publicTechnicianContributionsEnabled: bool,
     *     customerTechnicianContributionsEnabled: bool
     * }
     */
    public function getKnowledgeBaseSettings(): array
    {
        $legacySmartTipsEnabled = $this->getBool(self::FEATURE_KNOWLEDGE_BASE_SMART_TIPS_ENABLED, true);
        $legacyFaqEnabled = $this->getBool(self::FEATURE_KNOWLEDGE_BASE_FAQ_ENABLED, true);

        return [
            'publicEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_PUBLIC_ENABLED, false),
            'customerEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_CUSTOMER_ENABLED, true),
            'publicSmartTipsEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_PUBLIC_SMART_TIPS_ENABLED, $legacySmartTipsEnabled),
            'publicFaqEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_PUBLIC_FAQ_ENABLED, $legacyFaqEnabled),
            'customerSmartTipsEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_CUSTOMER_SMART_TIPS_ENABLED, $legacySmartTipsEnabled),
            'customerFaqEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_CUSTOMER_FAQ_ENABLED, $legacyFaqEnabled),
            'publicTechnicianContributionsEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_PUBLIC_TECHNICIAN_CONTRIBUTIONS_ENABLED, false),
            'customerTechnicianContributionsEnabled' => $this->getBool(self::FEATURE_KNOWLEDGE_BASE_CUSTOMER_TECHNICIAN_CONTRIBUTIONS_ENABLED, false),
        ];
    }

    /**
     * @return array{technicianContributionsEnabled: bool}
     */
    public function getNewsSettings(): array
    {
        return [
            'technicianContributionsEnabled' => $this->getBool(self::FEATURE_NEWS_TECHNICIAN_CONTRIBUTIONS_ENABLED, false),
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     intro: string,
     *     links: list<array{icon: string, title: string, url: string}>,
     *     linkLines: list<string>
     * }
     */
    public function getHomeSupportWidgetSettings(): array
    {
        $title = $this->getString(self::HOME_SUPPORT_WIDGET_TITLE, $this->translator->trans('home.support_widget.title'));
        $intro = $this->getString(self::HOME_SUPPORT_WIDGET_INTRO, $this->translator->trans('home.support_widget.intro'));
        $linkLines = $this->normalizeLineList($this->getString(
            self::HOME_SUPPORT_WIDGET_LINKS,
            implode("\n", [
                sprintf('spark | %s | /kunskapsbas', $this->translator->trans('home.support_widget.link.faq')),
                sprintf('book | %s | /kunskapsbas', $this->translator->trans('home.support_widget.link.knowledge_base')),
                sprintf('check | %s | /kontakta-oss', $this->translator->trans('home.support_widget.link.contact')),
            ]),
        ));

        return [
            'title' => '' !== $title ? $title : $this->translator->trans('home.support_widget.title'),
            'intro' => '' !== $intro ? $intro : $this->translator->trans('home.support_widget.intro'),
            'links' => $this->normalizeSupportLinks($linkLines),
            'linkLines' => $linkLines,
        ];
    }

    /**
     * @return array{title: string, intro: string, maxItems: int}
     */
    public function getHomepageStatusSectionSettings(): array
    {
        return [
            'title' => $this->getString(self::HOME_STATUS_SECTION_TITLE, $this->translator->trans('home.status_section.default_title')),
            'intro' => $this->getString(self::HOME_STATUS_SECTION_INTRO, $this->translator->trans('home.status_section.default_intro')),
            'maxItems' => min(12, max(1, $this->getInt(self::HOME_STATUS_SECTION_MAX_ITEMS, 4))),
        ];
    }

    /**
     * @return array{name: string, logoPath: string, footerText: string}
     */
    public function getSiteBrandingSettings(): array
    {
        $name = trim($this->getString(self::SITE_BRAND_NAME, 'Driftpunkt'));
        $logoPath = trim($this->getString(self::SITE_BRAND_LOGO_PATH, '/assets/branding/driftpunkt-logo-full.png'));
        $footerText = trim($this->getString(self::SITE_FOOTER_TEXT, ''));

        if ('' === $name) {
            $name = 'Driftpunkt';
        }

        if ('' === $logoPath) {
            $logoPath = '/assets/branding/driftpunkt-logo-full.png';
        }

        return [
            'name' => $name,
            'logoPath' => $logoPath,
            'footerText' => $footerText,
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     intro: string,
     *     body: string,
     *     effectiveDate: string,
     *     contactEmail: string,
     *     externalEnabled: bool,
     *     externalUrl: string
     * }
     */
    public function getPrivacyPolicySettings(): array
    {
        $contactPageSettings = $this->getContactPageSettings();
        $contactEmail = trim($this->getString(
            self::PRIVACY_POLICY_CONTACT_EMAIL,
            (string) ($contactPageSettings['email'] ?? ''),
        ));

        if ('' === $contactEmail) {
            $contactEmail = 'privacy@example.com';
        }

        return [
            'title' => $this->getString(self::PRIVACY_POLICY_TITLE, 'Integritetspolicy'),
            'intro' => $this->getString(
                self::PRIVACY_POLICY_INTRO,
                'Här beskriver vi hur personuppgifter behandlas när någon använder webbplatsen, kontaktar supporten eller använder kundportalen.',
            ),
            'body' => $this->getString(
                self::PRIVACY_POLICY_BODY,
                $this->defaultPrivacyPolicyBody($contactEmail),
            ),
            'effectiveDate' => $this->getString(
                self::PRIVACY_POLICY_EFFECTIVE_DATE,
                (new \DateTimeImmutable('today'))->format('Y-m-d'),
            ),
            'contactEmail' => $contactEmail,
            'externalEnabled' => $this->getBool(self::FEATURE_PRIVACY_POLICY_EXTERNAL_ENABLED, false),
            'externalUrl' => trim($this->getString(self::PRIVACY_POLICY_EXTERNAL_URL, '')),
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     intro: string,
     *     body: string,
     *     effectiveDate: string,
     *     contactEmail: string,
     *     externalEnabled: bool,
     *     externalUrl: string
     * }
     */
    public function getTermsPageSettings(): array
    {
        $contactPageSettings = $this->getContactPageSettings();
        $contactEmail = trim($this->getString(
            self::TERMS_PAGE_CONTACT_EMAIL,
            (string) ($contactPageSettings['email'] ?? ''),
        ));

        if ('' === $contactEmail) {
            $contactEmail = 'support@example.com';
        }

        return [
            'title' => $this->getString(self::TERMS_PAGE_TITLE, 'Användarvillkor'),
            'intro' => $this->getString(
                self::TERMS_PAGE_INTRO,
                'Här beskriver vi villkoren för att använda webbplatsen, kundportalen och Driftpunkts supporttjänster.',
            ),
            'body' => $this->getString(
                self::TERMS_PAGE_BODY,
                $this->defaultTermsPageBody($contactEmail),
            ),
            'effectiveDate' => $this->getString(
                self::TERMS_PAGE_EFFECTIVE_DATE,
                (new \DateTimeImmutable('today'))->format('Y-m-d'),
            ),
            'contactEmail' => $contactEmail,
            'externalEnabled' => $this->getBool(self::FEATURE_TERMS_PAGE_EXTERNAL_ENABLED, false),
            'externalUrl' => trim($this->getString(self::TERMS_PAGE_EXTERNAL_URL, '')),
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     intro: string,
     *     body: string,
     *     effectiveDate: string,
     *     contactEmail: string,
     *     externalEnabled: bool,
     *     externalUrl: string
     * }
     */
    public function getCookiePolicySettings(): array
    {
        $contactPageSettings = $this->getContactPageSettings();
        $contactEmail = trim($this->getString(
            self::COOKIE_POLICY_CONTACT_EMAIL,
            (string) ($contactPageSettings['email'] ?? ''),
        ));

        if ('' === $contactEmail) {
            $contactEmail = 'support@example.com';
        }

        return [
            'title' => $this->getString(self::COOKIE_POLICY_TITLE, 'Cookiepolicy'),
            'intro' => $this->getString(
                self::COOKIE_POLICY_INTRO,
                'Här beskriver vi hur cookies, sessionsdata och liknande tekniker används på webbplatsen och i kundportalen.',
            ),
            'body' => $this->getString(
                self::COOKIE_POLICY_BODY,
                $this->defaultCookiePolicyBody($contactEmail),
            ),
            'effectiveDate' => $this->getString(
                self::COOKIE_POLICY_EFFECTIVE_DATE,
                (new \DateTimeImmutable('today'))->format('Y-m-d'),
            ),
            'contactEmail' => $contactEmail,
            'externalEnabled' => $this->getBool(self::FEATURE_COOKIE_POLICY_EXTERNAL_ENABLED, false),
            'externalUrl' => trim($this->getString(self::COOKIE_POLICY_EXTERNAL_URL, '')),
        ];
    }

    /**
     * @return array{lookaheadDays: int}
     */
    public function getMaintenanceNoticeSettings(): array
    {
        return [
            'lookaheadDays' => min(30, max(1, $this->getInt(self::MAINTENANCE_NOTICE_LOOKAHEAD_DAYS, 7))),
        ];
    }

    /**
     * @return array{
     *     items: list<array{
     *         name: string,
     *         icon: string,
     *         status: string,
     *         stateLabel: ?string,
     *         linkLabel: ?string,
     *         url: ?string,
     *         pill: ?string
     *     }>,
     *     rawLines: list<string>
     * }
     */
    public function getPublicStatusSettings(): array
    {
        $rawLines = $this->normalizeLineList($this->getString(
            self::PUBLIC_STATUS_ITEMS,
            implode("\n", [
                sprintf(
                    'display | %s | %s | %s |  |  | ',
                    $this->translator->trans('status.defaults.site.name'),
                    $this->translator->trans('status.defaults.site.status'),
                    $this->translator->trans('status.defaults.site.state'),
                ),
                sprintf(
                    'db | %s | %s |  | %s | /driftstatus | %s',
                    $this->translator->trans('status.defaults.database.name'),
                    $this->translator->trans('status.defaults.database.status'),
                    $this->translator->trans('status.defaults.link_label'),
                    $this->translator->trans('status.defaults.database.pill'),
                ),
                sprintf(
                    'api | %s | %s |  | %s | /driftstatus | %s',
                    $this->translator->trans('status.defaults.api.name'),
                    $this->translator->trans('status.defaults.api.status'),
                    $this->translator->trans('status.defaults.link_label'),
                    $this->translator->trans('status.defaults.api.pill'),
                ),
                sprintf(
                    'gear | %s | %s | %s |  |  | ',
                    $this->translator->trans('status.defaults.auth.name'),
                    $this->translator->trans('status.defaults.auth.status'),
                    $this->translator->trans('status.defaults.auth.state'),
                ),
                sprintf(
                    'spark | %s | %s |  | %s | /driftstatus | ',
                    $this->translator->trans('status.defaults.mail.name'),
                    $this->translator->trans('status.defaults.mail.status'),
                    $this->translator->trans('status.defaults.link_label'),
                ),
                sprintf(
                    'book | %s | %s | %s |  |  | %s',
                    $this->translator->trans('status.defaults.backup.name'),
                    $this->translator->trans('status.defaults.backup.status'),
                    $this->translator->trans('status.defaults.backup.state'),
                    $this->translator->trans('status.defaults.backup.pill'),
                ),
                sprintf(
                    'check | %s | %s | %s |  |  | ',
                    $this->translator->trans('status.defaults.monitoring.name'),
                    $this->translator->trans('status.defaults.monitoring.status'),
                    $this->translator->trans('status.defaults.monitoring.state'),
                ),
            ]),
        ));

        return [
            'items' => $this->normalizePublicStatusItems($rawLines),
            'rawLines' => $rawLines,
        ];
    }

    /**
     * @return array{
     *     monitors: list<array{
     *         type: string,
     *         name: string,
     *         target: string,
     *         details: ?string,
     *         linkLabel: ?string,
     *         linkUrl: ?string,
     *         icon: string,
     *         showOnHomepage: bool
     *     }>,
     *     rawLines: list<string>
     * }
     */
    public function getStatusMonitorSettings(): array
    {
        $rawLines = $this->normalizeLineList($this->getString(
            self::STATUS_MONITORS,
            '',
        ));

        return [
            'monitors' => $this->normalizeStatusMonitorItems($rawLines),
            'rawLines' => $rawLines,
        ];
    }

    /**
     * @return array{
     *     showSystemCheckedAt: bool,
     *     showSystemSource: bool,
     *     showRecentUpdates: bool,
     *     recentUpdatesTitle: string,
     *     recentUpdatesIntro: string,
     *     recentUpdatesMaxItems: int,
     *     showImpact: bool,
     *     impactTitle: string,
     *     impactIntro: string,
     *     impactItems: list<array{
     *         name: string,
     *         normalLabel: string,
     *         upcomingLabel: string,
     *         activeLabel: string,
     *         description: ?string
     *     }>,
     *     impactLines: list<string>,
     *     showHistory: bool,
     *     showSubscribeBox: bool,
     *     subscribeTitle: string,
     *     subscribeText: string,
     *     subscribeLinkLabel: string,
     *     subscribeLinkUrl: string
     * }
     */
    public function getStatusPageSettings(): array
    {
        $impactLines = $this->normalizeLineList($this->getString(
            self::STATUS_PAGE_IMPACT_ITEMS,
            "Kundlogin | Tillganglig | Planerat avbrott | Tillfalligt pausad | Kundportalen och sjalvservice kan paverkas under driftfonster.\n".
            "Teknikerlogin | Tillganglig | Planerat avbrott | Tillfalligt pausad | Tekniker kan folja driftsidan men inloggning stoppas under aktivt underhall.\n".
            "Adminyta | Tillganglig | Tillganglig | Tillganglig for verifiering | Admin kan verifiera lage och publicera uppdateringar.\n".
            "Support och kontakt | Oppet | Oppet | Oppet | Kontaktsidan och driftinformationen halls alltid oppna.",
        ));

        return [
            'showSystemCheckedAt' => $this->getBool(self::STATUS_PAGE_SHOW_SYSTEM_CHECKED_AT, true),
            'showSystemSource' => $this->getBool(self::STATUS_PAGE_SHOW_SYSTEM_SOURCE, true),
            'showRecentUpdates' => $this->getBool(self::STATUS_PAGE_SHOW_RECENT_UPDATES, true),
            'recentUpdatesTitle' => $this->getString(self::STATUS_PAGE_RECENT_UPDATES_TITLE, 'Senaste driftuppdateringar'),
            'recentUpdatesIntro' => $this->getString(self::STATUS_PAGE_RECENT_UPDATES_INTRO, 'Snabba uppdateringar och publicerade driftnyheter samlade pa ett stalle.'),
            'recentUpdatesMaxItems' => min(8, max(1, $this->getInt(self::STATUS_PAGE_RECENT_UPDATES_MAX_ITEMS, 4))),
            'showImpact' => $this->getBool(self::STATUS_PAGE_SHOW_IMPACT, true),
            'impactTitle' => $this->getString(self::STATUS_PAGE_IMPACT_TITLE, 'Paverkan just nu'),
            'impactIntro' => $this->getString(self::STATUS_PAGE_IMPACT_INTRO, 'Det har ar den tydligaste bilden av vad som fungerar normalt, vad som ar planerat och vad som ar pausat.'),
            'impactItems' => $this->normalizeStatusPageImpactItems($impactLines),
            'impactLines' => $impactLines,
            'showHistory' => $this->getBool(self::STATUS_PAGE_SHOW_HISTORY, true),
            'showSubscribeBox' => $this->getBool(self::STATUS_PAGE_SHOW_SUBSCRIBE_BOX, true),
            'subscribeTitle' => $this->getString(self::STATUS_PAGE_SUBSCRIBE_TITLE, 'Fa driftinfo snabbare'),
            'subscribeText' => $this->getString(self::STATUS_PAGE_SUBSCRIBE_TEXT, 'Peka kunder och tekniker till nyhetsflodet eller er externa statussida for lopande uppdateringar under storningar och underhall.'),
            'subscribeLinkLabel' => $this->getString(self::STATUS_PAGE_SUBSCRIBE_LINK_LABEL, 'Se alla nyheter'),
            'subscribeLinkUrl' => $this->getString(self::STATUS_PAGE_SUBSCRIBE_LINK_URL, '/nyheter'),
        ];
    }

    /**
     * @return array{
     *     heroPill: string,
     *     title: string,
     *     subtitle: string,
     *     email: string,
     *     phone: string,
     *     phoneHref: string,
     *     hours: string,
     *     primaryCtaLabel: string,
     *     primaryCtaUrl: string,
     *     secondaryCtaLabel: string,
     *     secondaryCtaUrl: string,
     *     statusLines: list<string>,
     *     statusLinesRaw: list<string>,
     *     channelCards: list<array{eyebrow: string, title: string, description: string, linkLabel: string}>,
     *     channelCardsRaw: list<string>,
     *     quickHelpTitle: string,
     *     quickHelpIntro: string,
     *     quickHelpSteps: list<array{badge: string, title: string, description: string}>,
     *     quickHelpStepsRaw: list<string>,
     *     quickHelpNote: string,
     *     priorityTitle: string,
     *     priorityIntro: string,
     *     priorityLinkLabels: list<string>,
     *     priorityLinkLabelsRaw: list<string>,
     *     whenTitle: string,
     *     whenItems: list<array{badge: string, title: string, description: string}>,
     *     whenItemsRaw: list<string>,
     *     extraHelpTitle: string,
     *     extraHelpIntro: string
     * }
     */
    public function getContactPageSettings(): array
    {
        $phone = $this->getString(self::CONTACT_PAGE_PHONE, '+46 10 123 45 67');
        $phoneHref = preg_replace('/[^0-9+]/', '', $phone) ?: '+46101234567';
        $statusLinesRaw = $this->normalizeLineList($this->getString(
            self::CONTACT_PAGE_STATUS_LINES,
            "Supporten är tillgänglig\nNormal svarstid: inom 1 arbetsdag\nAkuta driftärenden: prioriteras direkt",
        ));
        $channelCardsRaw = $this->normalizeLineList($this->getString(
            self::CONTACT_PAGE_CHANNEL_CARDS,
            "Direktkontakt | E-post | För allmän support, onboardingfrågor och uppföljning av befintliga ärenden. | Skicka e-post\nSnabbast vid behov | Telefon | När du behöver snabb guidning, prioriterad kontakt eller vill tala direkt med teamet. | Ring oss\nBäst för ärenden | Kundportal | Det bästa valet för supportärenden där du vill kunna följa status, svar och historik över tid. | Öppna portalen",
        ));
        $quickHelpStepsRaw = $this->normalizeLineList($this->getString(
            self::CONTACT_PAGE_QUICK_HELP_STEPS,
            "1 | Beskriv problemet | Vad fungerar inte, vem påverkas och hur märks felet i vardagen?\n2 | Dela kontext | Skicka med skärmbilder, feltext eller vilka steg som redan har testats.\n3 | Välj rätt väg | Portal för supportärenden, e-post för frågor och telefon för mer brådskande kontakt.",
        ));
        $priorityLinkLabelsRaw = $this->normalizeLineList($this->getString(
            self::CONTACT_PAGE_PRIORITY_LINK_LABELS,
            "E-post för support\nRing supporten\nSe driftnyheter",
        ));
        $whenItemsRaw = $this->normalizeLineList($this->getString(
            self::CONTACT_PAGE_WHEN_ITEMS,
            "? | Allmän rådgivning | Frågor om upplägg, arbetssätt, onboarding eller hur Driftpunkt kan användas mer effektivt.\n✓ | Supportärenden | När något inte fungerar, behöver följas upp eller kräver teknisk felsökning.\n! | Akuta incidenter | När flera användare påverkas, tjänster ligger nere eller verksamheten stoppas.",
        ));

        return [
            'heroPill' => $this->getString(self::CONTACT_PAGE_HERO_PILL, 'Kontakt & support'),
            'title' => $this->getString(self::CONTACT_PAGE_TITLE, 'Vi hjälper dig vidare'),
            'subtitle' => $this->getString(self::CONTACT_PAGE_SUBTITLE, 'Kontakta Driftpunkt på det sätt som passar bäst för ditt ärende. Här hittar du tydliga kontaktvägar för support, driftfrågor, nya kunder och allmän rådgivning i samma lugna och professionella uttryck som resten av sajten.'),
            'email' => $this->getString(self::CONTACT_PAGE_EMAIL, 'support@driftpunkt.local'),
            'phone' => $phone,
            'phoneHref' => $phoneHref,
            'hours' => $this->getString(self::CONTACT_PAGE_HOURS, 'Måndag till fredag 08:00-17:00. Akuta incidenter utanför ordinarie tid bör märkas tydligt vid kontakt.'),
            'primaryCtaLabel' => $this->getString(self::CONTACT_PAGE_PRIMARY_CTA_LABEL, 'Skapa supportärende'),
            'primaryCtaUrl' => $this->getString(self::CONTACT_PAGE_PRIMARY_CTA_URL, '/login?role=customer'),
            'secondaryCtaLabel' => $this->getString(self::CONTACT_PAGE_SECONDARY_CTA_LABEL, 'Sök i kunskapsbanken'),
            'secondaryCtaUrl' => $this->getString(self::CONTACT_PAGE_SECONDARY_CTA_URL, '/kunskapsbas'),
            'statusLines' => [] !== $statusLinesRaw ? $statusLinesRaw : ['Supporten är tillgänglig'],
            'statusLinesRaw' => $statusLinesRaw,
            'channelCards' => $this->normalizeContactCards($channelCardsRaw),
            'channelCardsRaw' => $channelCardsRaw,
            'quickHelpTitle' => $this->getString(self::CONTACT_PAGE_QUICK_HELP_TITLE, 'Så når du rätt snabbare'),
            'quickHelpIntro' => $this->getString(self::CONTACT_PAGE_QUICK_HELP_INTRO, 'För att vi ska kunna hjälpa dig så effektivt som möjligt är det bra att beskriva vad som påverkas, när problemet började och om det finns ett felmeddelande. Har du skärmbilder eller loggutdrag går det ofta snabbare att felsöka direkt.'),
            'quickHelpSteps' => $this->normalizeBadgeItems($quickHelpStepsRaw),
            'quickHelpStepsRaw' => $quickHelpStepsRaw,
            'quickHelpNote' => $this->getString(self::CONTACT_PAGE_QUICK_HELP_NOTE, 'Tydlig kontext och rätt kanal gör nästan alltid att vi kan hjälpa dig snabbare direkt.'),
            'priorityTitle' => $this->getString(self::CONTACT_PAGE_PRIORITY_TITLE, 'Öppettider & prioritering'),
            'priorityIntro' => $this->getString(self::CONTACT_PAGE_PRIORITY_INTRO, 'Vi hanterar inkommande frågor löpande under arbetsdagen. Kritiska driftärenden prioriteras före allmänna frågor och planeringsärenden.'),
            'priorityLinkLabels' => array_pad($priorityLinkLabelsRaw, 3, ''),
            'priorityLinkLabelsRaw' => $priorityLinkLabelsRaw,
            'whenTitle' => $this->getString(self::CONTACT_PAGE_WHEN_TITLE, 'När ska du kontakta oss?'),
            'whenItems' => $this->normalizeBadgeItems($whenItemsRaw),
            'whenItemsRaw' => $whenItemsRaw,
            'extraHelpTitle' => $this->getString(self::CONTACT_PAGE_EXTRA_HELP_TITLE, 'Fler vägar till hjälp'),
            'extraHelpIntro' => $this->getString(self::CONTACT_PAGE_EXTRA_HELP_INTRO, 'Vi har också samlat de viktigaste genvägarna som admin redan har valt ut för startsidans hjälpruta.'),
        ];
    }

    /**
     * @param list<string> $lines
     * @return list<array{eyebrow: string, title: string, description: string, linkLabel: string}>
     */
    private function normalizeContactCards(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 4));
            $eyebrow = $parts[0] ?? '';
            $title = $parts[1] ?? '';
            $description = $parts[2] ?? '';
            $linkLabel = $parts[3] ?? '';

            if ('' === $title || '' === $description) {
                continue;
            }

            $items[] = [
                'eyebrow' => $eyebrow,
                'title' => $title,
                'description' => $description,
                'linkLabel' => $linkLabel,
            ];
        }

        return array_pad($items, 3, [
            'eyebrow' => '',
            'title' => '',
            'description' => '',
            'linkLabel' => '',
        ]);
    }

    /**
     * @param list<string> $lines
     * @return list<array{badge: string, title: string, description: string}>
     */
    private function normalizeBadgeItems(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            $badge = $parts[0] ?? '';
            $title = $parts[1] ?? '';
            $description = $parts[2] ?? '';

            if ('' === $title || '' === $description) {
                continue;
            }

            $items[] = [
                'badge' => $badge,
                'title' => $title,
                'description' => $description,
            ];
        }

        return $items;
    }

    /**
     * @param list<string> $lines
     * @return list<array{icon: string, title: string, url: string}>
     */
    private function normalizeSupportLinks(array $lines): array
    {
        $allowedIcons = ['spark', 'book', 'check', 'chat', 'gear', 'api', 'display', 'db'];
        $items = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            $icon = $parts[0] ?? 'check';
            $title = $this->normalizeDecoratedLabel($parts[1] ?? '');
            $url = $parts[2] ?? '';

            if ('' === $title || '' === $url) {
                continue;
            }

            // Keep old saved homepage support links working after the dedicated contact page was introduced.
            if ('/portal' === $url && 'kontakta support' === mb_strtolower($title)) {
                $url = '/kontakta-oss';
            }

            $items[] = [
                'icon' => \in_array($icon, $allowedIcons, true) ? $icon : 'check',
                'title' => $title,
                'url' => $url,
            ];
        }

        return [] !== $items ? $items : [
            ['icon' => 'spark', 'title' => $this->translator->trans('home.support_widget.link.faq'), 'url' => '/kunskapsbas'],
            ['icon' => 'book', 'title' => $this->translator->trans('home.support_widget.link.knowledge_base'), 'url' => '/kunskapsbas'],
            ['icon' => 'check', 'title' => $this->translator->trans('home.support_widget.link.contact'), 'url' => '/portal'],
        ];
    }

    private function defaultPrivacyPolicyBody(string $contactEmail): string
    {
        return implode("\n\n", [
            '## Personuppgiftsansvarig'."\n".
            'Den organisation som driver denna webbplats och tillhorande kundportal ar personuppgiftsansvarig for den behandling av personuppgifter som beskrivs i denna policy. Om du har fragor om hur uppgifterna hanteras kan du kontakta oss via `'.$contactEmail.'`.',
            '## Nar policyn galler'."\n".
            'Policyn galler for behandling av personuppgifter nar du:'."\n".
            '- besoker den publika webbplatsen'."\n".
            '- kontaktar supporten via formulär, e-post eller telefon'."\n".
            '- skapar konto eller loggar in i kundportalen'."\n".
            '- skapar, foljer eller uppdaterar arenden'."\n".
            '- prenumererar pa driftinformation, nyheter eller annan kommunikation',
            '## Vilka uppgifter vi kan behandla'."\n".
            'Beroende pa hur du använder tjänsten kan vi behandla foljande kategorier av uppgifter:'."\n".
            '- namn, foretag, roll och kontaktuppgifter som e-postadress och telefonnummer'."\n".
            '- kontouppgifter som anvandar-id, inloggningshistorik och behorigheter'."\n".
            '- ärendeinformation, meddelanden, bilagor och annan information du sjalv skickar in'."\n".
            '- tekniska uppgifter som IP-adress, webblasartyp, enhet, loggar och tidpunkter for aktivitet'."\n".
            '- uppgifter som behovs for att felsoka, skydda och forbattra tjänsten',
            '## Andamal med behandlingen'."\n".
            'Vi behandlar personuppgifter for att kunna:'."\n".
            '- tillhandahalla webbplats, kundportal och support'."\n".
            '- hantera konton, behorigheter och inloggning'."\n".
            '- registrera, prioritera och folja upp arenden'."\n".
            '- kommunicera om driftstatus, support, incidenter och planerat underhall'."\n".
            '- uppfylla rattsliga skyldigheter, forebygga missbruk och uppratthalla sakerhet'."\n".
            '- analysera, forbattra och utveckla tjänsten',
            '## Rattslig grund'."\n".
            'Behandlingen sker normalt med stod av en eller flera av dessa rattsliga grunder:'."\n".
            '- avtal: for att kunna leverera avtalad tjänst eller support'."\n".
            '- rattslig forpliktelse: nar vi maste spara eller lamna ut uppgifter enligt lag'."\n".
            '- berattigat intresse: for drift, sakerhet, loggning, felsokning och utveckling'."\n".
            '- samtycke: nar en viss behandling bygger pa ett aktivt godkannande, som kan aterkallas',
            '## Kallor till personuppgifter'."\n".
            'Vi samlar i forsta hand in uppgifter direkt fran dig, ditt foretag eller nagon som administrerar ert konto. Vissa tekniska uppgifter skapas automatiskt nar du anvander webbplatsen eller kundportalen.',
            '## Mottagare och personuppgiftsbitraden'."\n".
            'Uppgifter delas endast nar det behovs for att tillhandahalla tjänsten, till exempel med driftleverantorer, supportverktyg, e-postleverantorer, hostingpartner eller andra personuppgiftsbitraden som behandlar uppgifter enligt avtal och instruktioner. Uppgifter kan ocksa lamnas ut nar lag eller myndighetsbeslut kraver det.',
            '## Overforing utanfor EU/EES'."\n".
            'Vi stravar efter att behandla personuppgifter inom EU/EES. Om overforing till land utanfor EU/EES skulle bli aktuell ska den ske med laglig skyddsniva, exempelvis genom EU-kommissionens standardavtalsklausuler eller annat tillatet skydd enligt dataskyddsreglerna.',
            '## Lagringstid'."\n".
            'Vi sparar personuppgifter sa lange det behovs for de andamal som anges i denna policy, sa lange det finns ett aktivt kundforhallande eller sa lange vi maste spara uppgifter enligt lag, avtal, bokforingskrav, sakerhetsbehov eller for att hantera reklamationer och rattsliga ansprak.',
            '## Dina rattigheter'."\n".
            'Du har enligt dataskyddsreglerna ratt att begara information om vilka personuppgifter vi behandlar om dig och i vissa fall ratt att fa felaktiga uppgifter rattade, uppgifter raderade, behandlingen begransad, invanda mot viss behandling eller fa ut uppgifter for dataportabilitet. Om behandlingen bygger pa samtycke kan du nar som helst aterkalla samtycket for framtiden.',
            '## Klagomal till tillsynsmyndighet'."\n".
            'Om du anser att behandlingen av dina personuppgifter strider mot gallande regler har du ratt att lamna klagomal till Integritetsskyddsmyndigheten, IMY, via [imy.se](https://www.imy.se/).',
            '## Informationssakerhet'."\n".
            'Vi arbetar med tekniska och organisatoriska sakerhetsatgarder for att skydda personuppgifter mot obehorig atkomst, forlust, andring och annan otillaten behandling. Atkomst till uppgifter begransas till personer som behover den for sitt arbete.',
            '## Cookies och loggar'."\n".
            'Webbplatsen och kundportalen kan anvanda nodvandiga cookies, sessionsdata och loggar for att fungera korrekt, skydda inloggningen, felsoka problem och forsta hur tjänsten används. Om ytterligare cookies eller spårning används ska det beskrivas tydligt och, nar det kravs, hanteras med samtycke.',
            '## Andringar i policyn'."\n".
            'Vi kan uppdatera denna integritetspolicy nar tjänsten eller regelverket andras. Den senaste versionen finns alltid publicerad pa denna sida.',
        ]);
    }

    private function defaultTermsPageBody(string $contactEmail): string
    {
        return implode("\n\n", [
            '## Om villkoren'."\n".
            'Dessa användarvillkor gäller för användning av denna webbplats, kundportalen och tillhörande supporttjänster. Genom att använda tjänsten accepterar användaren dessa villkor.',
            '## Tjänstens syfte'."\n".
            'Tjänsten används för att hantera supportärenden, driftinformation, kommunikation och relaterade funktioner mellan Driftpunkt och kunder eller andra behöriga användare.',
            '## Konto och behörighet'."\n".
            '- användaren ansvarar för att lämnade uppgifter är korrekta och uppdaterade'."\n".
            '- inloggningsuppgifter ska hanteras säkert och får inte delas med obehöriga'."\n".
            '- konton får endast användas av den person eller organisation som tilldelats behörigheten'."\n".
            '- Driftpunkt får stänga av eller begränsa konto vid misstanke om missbruk, säkerhetsrisk eller brott mot villkoren',
            '## Tillåten användning'."\n".
            'Tjänsten får endast användas för legitima support- och driftrelaterade ändamål. Det är inte tillåtet att försöka störa tjänsten, kringgå säkerhetsfunktioner, ladda upp skadlig kod eller använda tjänsten på sätt som kan skada Driftpunkt, andra kunder eller tredje man.',
            '## Kundens ansvar'."\n".
            'Användaren ansvarar för innehållet i ärenden, meddelanden och bilagor som skickas in. Material får inte vara olagligt, kränkande, vilseledande eller innehålla skadlig kod. Användaren ansvarar också för att inte lämna fler personuppgifter än nödvändigt.',
            '## Tillgänglighet och förändringar'."\n".
            'Driftpunkt strävar efter hög tillgänglighet men garanterar inte att tjänsten alltid är fri från avbrott eller fel. Planerat underhåll, säkerhetsåtgärder, uppdateringar eller oförutsedda incidenter kan påverka tillgängligheten.',
            '## Immateriella rättigheter'."\n".
            'Webbplatsen, kundportalen, designen, koden, texterna och övrigt material tillhör Driftpunkt eller dess licensgivare om inget annat anges. Innehåll får inte kopieras, säljas, spridas eller användas utanför avsett syfte utan tillstånd.',
            '## Ansvarsbegränsning'."\n".
            'Driftpunkt ansvarar inte för indirekta skador, utebliven vinst, dataförlust eller annan följdskada, i den utsträckning sådan begränsning är tillåten enligt lag. Tjänsten tillhandahålls i befintligt skick om inget annat följer av särskilt avtal.',
            '## Personuppgifter'."\n".
            'Behandling av personuppgifter regleras i vår [Integritetspolicy](/integritetspolicy). Om användningen av tjänsten innebär behandling av personuppgifter för kunds räkning kan ytterligare avtal eller instruktioner behöva gälla.',
            '## Avstängning och avslut'."\n".
            'Driftpunkt får tillfälligt eller permanent stänga av tillgång till tjänsten vid säkerhetsincidenter, avtalsbrott, utebliven betalning, missbruk eller när det krävs för drift och underhåll.',
            '## Tillämplig lag och tvister'."\n".
            'Dessa villkor ska tolkas enligt svensk rätt, om inte annat följer av tvingande lag eller särskilt avtal. Tvister ska i första hand lösas genom dialog mellan parterna.',
            '## Kontakt'."\n".
            'Om du har frågor om villkoren kan du kontakta oss via `'.$contactEmail.'`.',
            '## Ändringar i villkoren'."\n".
            'Vi kan uppdatera dessa villkor när tjänsten utvecklas, lagkrav förändras eller nya funktioner införs. Den senaste versionen publiceras alltid på denna sida.',
        ]);
    }

    private function defaultCookiePolicyBody(string $contactEmail): string
    {
        return implode("\n\n", [
            '## Om cookies'."\n".
            'Cookies är små textfiler som lagras i webbläsaren när du besöker en webbplats. De används ofta för att få sidor att fungera, komma ihåg val och ge information om hur tjänsten används.',
            '## Vad vi använder cookies till'."\n".
            'Webbplatsen och kundportalen kan använda cookies, sessionsdata eller liknande tekniker för att:'."\n".
            '- hålla användaren inloggad under en aktiv session'."\n".
            '- skydda formulär och inloggning mot missbruk'."\n".
            '- komma ihåg språkval eller andra grundläggande inställningar'."\n".
            '- felsöka, säkra och förbättra tjänsten'."\n".
            '- i förekommande fall mäta användning och prestanda',
            '## Typer av cookies'."\n".
            'Tjänsten kan använda följande kategorier:'."\n".
            '- nödvändiga cookies: krävs för att webbplatsen och kundportalen ska fungera'."\n".
            '- funktionscookies: sparar val som språk eller liknande preferenser'."\n".
            '- analyscookies: används för statistik och förbättringsarbete om sådana verktyg aktiveras'."\n".
            '- tredjepartscookies: kan förekomma om externa tjänster bäddas in eller används',
            '## Lagringstid'."\n".
            'Vissa cookies raderas när du stänger webbläsaren och andra ligger kvar under en viss tid. Lagringstiden beror på cookiens syfte och tekniska funktion.',
            '## Tredjepartstjänster'."\n".
            'Om externa tjänster används, exempelvis för analys, inbäddat innehåll eller driftövervakning, kan dessa sätta egna cookies eller samla in teknisk information enligt sina egna villkor.',
            '## Hur du kan hantera cookies'."\n".
            'Du kan själv styra och radera cookies i din webbläsare. Om du blockerar nödvändiga cookies kan delar av webbplatsen eller kundportalen sluta fungera korrekt.',
            '## Samtycke'."\n".
            'Om tjänsten använder cookies som kräver samtycke enligt gällande regler ska sådant samtycke hämtas innan dessa aktiveras. Nödvändiga cookies kan användas utan samtycke när de krävs för att tjänsten ska fungera.',
            '## Mer information'."\n".
            'Om du har frågor om vår användning av cookies eller liknande tekniker kan du kontakta oss via `'.$contactEmail.'`.',
            '## Uppdateringar'."\n".
            'Vi kan uppdatera denna cookiepolicy när tjänsten förändras eller när regelverket kräver det. Den senaste versionen publiceras alltid på denna sida.',
        ]);
    }

    /**
     * @param list<string> $lines
     * @return list<array{
     *     name: string,
     *     icon: string,
     *     status: string,
     *     stateLabel: ?string,
     *     linkLabel: ?string,
     *     url: ?string,
     *     pill: ?string
     * }>
     */
    private function normalizePublicStatusItems(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 7));
            $icon = mb_strtolower($parts[0] ?? '');
            $name = $this->normalizeDecoratedLabel($parts[1] ?? '');
            $status = $this->normalizeDecoratedLabel($parts[2] ?? '');
            $stateLabel = $this->normalizeDecoratedLabel($parts[3] ?? '');
            $linkLabel = $this->normalizeDecoratedLabel($parts[4] ?? '');
            $url = $parts[5] ?? '';
            $pill = $this->normalizeDecoratedLabel($parts[6] ?? '');

            if ('' === $name || '' === $status) {
                continue;
            }

            if (!preg_match('/^[a-z0-9_-]+$/', $icon)) {
                $icon = 'display';
            }

            $items[] = [
                'name' => $name,
                'icon' => '' !== $icon ? $icon : 'display',
                'status' => $status,
                'stateLabel' => '' !== $stateLabel ? $stateLabel : null,
                'linkLabel' => '' !== $linkLabel ? $linkLabel : null,
                'url' => '' !== $url ? $url : null,
                'pill' => '' !== $pill ? $pill : null,
            ];
        }

        return $items;
    }

    /**
     * @param list<string> $lines
     * @return list<array{
     *     type: string,
     *     name: string,
     *     target: string,
     *     details: ?string,
     *     linkLabel: ?string,
     *     linkUrl: ?string,
     *     icon: string,
     *     showOnHomepage: bool
     * }>
     */
    private function normalizeStatusMonitorItems(array $lines): array
    {
        $items = [];
        $allowedTypes = ['manual', 'url', 'host', 'downdetector', 'isitdownrightnow'];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 8));
            $type = mb_strtolower($parts[0] ?? 'manual');
            $name = $this->normalizeDecoratedLabel($parts[1] ?? '');
            $target = $parts[2] ?? '';
            $details = $this->normalizeDecoratedLabel($parts[3] ?? '');
            $linkLabel = $this->normalizeDecoratedLabel($parts[4] ?? '');
            $linkUrl = $parts[5] ?? '';
            $icon = mb_strtolower($parts[6] ?? 'display');
            $showOnHomepage = $parts[7] ?? '1';

            if ('' === $name || '' === $target) {
                continue;
            }

            if (!\in_array($type, $allowedTypes, true)) {
                $type = 'manual';
            }

            if (!preg_match('/^[a-z0-9_-]+$/', $icon)) {
                $icon = 'display';
            }

            $items[] = [
                'type' => $type,
                'name' => $name,
                'target' => $target,
                'details' => '' !== $details ? $details : null,
                'linkLabel' => '' !== $linkLabel ? $linkLabel : null,
                'linkUrl' => '' !== $linkUrl ? $linkUrl : null,
                'icon' => '' !== $icon ? $icon : 'display',
                'showOnHomepage' => \in_array(mb_strtolower(trim($showOnHomepage)), ['1', 'true', 'yes', 'ja', 'on'], true),
            ];
        }

        return $items;
    }

    private function normalizeDecoratedLabel(string $value): string
    {
        $normalized = trim(html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $normalized = preg_replace('/^\s*[\p{So}\p{Sk}\p{Sm}\p{P}]+/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param list<string> $lines
     * @return list<array{
     *     name: string,
     *     normalLabel: string,
     *     upcomingLabel: string,
     *     activeLabel: string,
     *     description: ?string
     * }>
     */
    private function normalizeStatusPageImpactItems(array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 5));
            $name = $parts[0] ?? '';
            $normalLabel = $parts[1] ?? '';
            $upcomingLabel = $parts[2] ?? '';
            $activeLabel = $parts[3] ?? '';
            $description = $parts[4] ?? '';

            if ('' === $name) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'normalLabel' => '' !== $normalLabel ? $normalLabel : 'Tillganglig',
                'upcomingLabel' => '' !== $upcomingLabel ? $upcomingLabel : 'Planerat underhall',
                'activeLabel' => '' !== $activeLabel ? $activeLabel : 'Pausad',
                'description' => '' !== $description ? $description : null,
            ];
        }

        return $items;
    }
}
