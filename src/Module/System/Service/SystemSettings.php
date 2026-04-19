<?php

declare(strict_types=1);

namespace App\Module\System\Service;

use App\Module\System\Entity\SystemSetting;
use Doctrine\ORM\EntityManagerInterface;

final class SystemSettings
{
    public const SLA_FIRST_RESPONSE_WARNING_HOURS = 'sla.first_response_warning_hours';
    public const SLA_RESOLUTION_WARNING_HOURS = 'sla.resolution_warning_hours';
    public const FEATURE_TICKET_TEMPLATE_PLAYBOOK_ENABLED = 'feature.ticket_template_playbook_enabled';
    public const FEATURE_TICKET_TEMPLATE_CHECKLIST_ENABLED = 'feature.ticket_template_checklist_enabled';
    public const FEATURE_TICKET_TEMPLATE_CHECKLIST_PROGRESS_ENABLED = 'feature.ticket_template_checklist_progress_enabled';
    public const FEATURE_TICKET_TEMPLATE_CHECKLIST_CUSTOMER_VISIBLE = 'feature.ticket_template_checklist_customer_visible';
    public const FEATURE_TICKET_ATTACHMENTS_ENABLED = 'feature.ticket_attachments_enabled';
    public const FEATURE_TICKET_ATTACHMENTS_EXTERNAL_ENABLED = 'feature.ticket_attachments_external_enabled';
    public const TICKET_ATTACHMENTS_MAX_UPLOAD_MB = 'ticket_attachments.max_upload_mb';
    public const TICKET_ATTACHMENTS_STORAGE_PATH = 'ticket_attachments.storage_path';
    public const TICKET_ATTACHMENTS_ALLOWED_EXTENSIONS = 'ticket_attachments.allowed_extensions';
    public const TICKET_ATTACHMENTS_EXTERNAL_PROVIDER_LABEL = 'ticket_attachments.external_provider_label';
    public const TICKET_ATTACHMENTS_EXTERNAL_INSTRUCTIONS = 'ticket_attachments.external_instructions';
    public const FEATURE_TICKET_ATTACHMENTS_ZIP_ARCHIVING_ENABLED = 'feature.ticket_attachments_zip_archiving_enabled';
    public const TICKET_ATTACHMENTS_ZIP_ARCHIVE_AFTER_DAYS = 'ticket_attachments.zip_archive_after_days';
    public const FEATURE_CUSTOMER_SELF_REGISTRATION_ENABLED = 'feature.customer_self_registration_enabled';
    public const CUSTOMER_LOGIN_SMART_TIPS = 'customer_login.smart_tips';
    public const CUSTOMER_LOGIN_FAQ = 'customer_login.faq';
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

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
        $title = $this->getString(self::HOME_SUPPORT_WIDGET_TITLE, 'Behöver du hjälp?');
        $intro = $this->getString(self::HOME_SUPPORT_WIDGET_INTRO, 'Här hittar du de viktigaste genvägarna till hjälp, kunskap och kontakt.');
        $linkLines = $this->normalizeLineList($this->getString(
            self::HOME_SUPPORT_WIDGET_LINKS,
            "spark | Vanliga Frågor | /kunskapsbas\nbook | Kunskapsbank | /kunskapsbas\ncheck | Kontakta Support | /kontakta-oss",
        ));

        return [
            'title' => '' !== $title ? $title : 'Behöver du hjälp?',
            'intro' => '' !== $intro ? $intro : 'Här hittar du de viktigaste genvägarna till hjälp, kunskap och kontakt.',
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
            'title' => $this->getString(self::HOME_STATUS_SECTION_TITLE, 'Systemstatus'),
            'intro' => $this->getString(self::HOME_STATUS_SECTION_INTRO, 'Överblick över systemets hälsa.'),
            'maxItems' => min(12, max(1, $this->getInt(self::HOME_STATUS_SECTION_MAX_ITEMS, 4))),
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
            "display | Driftpunkt | Webbportal 6 min sedan | Alla system operativa |  |  | \n".
            "db | Databas | Databas 1 min sedan |  | Visa detaljer | /driftstatus | Underhåll\n".
            "api | API / Webhooks | Filserver / NAS, 8 min sedan |  | Visa detaljer | /driftstatus | Nyhet\n".
            "gear | Autentisering | SSO och lokal login, 3 min sedan | Stabil drift |  |  | \n".
            "spark | E-postgateway | Inkommande och utgående köer, 4 min sedan |  | Visa detaljer | /driftstatus | \n".
            "book | Backup / Arkiv | Senaste backup 02:00 i natt | Senaste körning lyckades |  |  | Backup\n".
            "check | Övervakning | Alla monitorer svarar, 1 min sedan | Allting grönt |  |  | ",
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
            $title = $parts[1] ?? '';
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
            ['icon' => 'spark', 'title' => 'Vanliga Frågor', 'url' => '/kunskapsbas'],
            ['icon' => 'book', 'title' => 'Kunskapsbank', 'url' => '/kunskapsbas'],
            ['icon' => 'check', 'title' => 'Kontakta Support', 'url' => '/portal'],
        ];
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
            $name = $parts[1] ?? '';
            $status = $parts[2] ?? '';
            $stateLabel = $parts[3] ?? '';
            $linkLabel = $parts[4] ?? '';
            $url = $parts[5] ?? '';
            $pill = $parts[6] ?? '';

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
            $name = $parts[1] ?? '';
            $target = $parts[2] ?? '';
            $details = $parts[3] ?? '';
            $linkLabel = $parts[4] ?? '';
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
