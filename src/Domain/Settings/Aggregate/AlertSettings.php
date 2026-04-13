<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Domain\Settings\Aggregate;

use Yammi\JobsMonitor\Domain\Settings\ValueObject\EmailRecipientList;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;

final class AlertSettings
{
    public function __construct(
        private readonly ?bool $enabled,
        private readonly ?string $sourceName,
        private readonly ?MonitorUrl $monitorUrl,
        private readonly EmailRecipientList $mailRecipients,
    ) {}

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function hasEnabledFlag(): bool
    {
        return $this->enabled !== null;
    }

    public function sourceName(): ?string
    {
        return $this->sourceName;
    }

    public function hasSourceName(): bool
    {
        return $this->sourceName !== null;
    }

    public function monitorUrl(): ?MonitorUrl
    {
        return $this->monitorUrl;
    }

    public function hasMonitorUrl(): bool
    {
        return $this->monitorUrl !== null;
    }

    public function mailRecipients(): EmailRecipientList
    {
        return $this->mailRecipients;
    }

    public function withEnabled(?bool $enabled): self
    {
        return new self(
            enabled: $enabled,
            sourceName: $this->sourceName,
            monitorUrl: $this->monitorUrl,
            mailRecipients: $this->mailRecipients,
        );
    }

    public function withSourceName(?string $sourceName): self
    {
        return new self(
            enabled: $this->enabled,
            sourceName: $sourceName,
            monitorUrl: $this->monitorUrl,
            mailRecipients: $this->mailRecipients,
        );
    }

    public function withMonitorUrl(?MonitorUrl $monitorUrl): self
    {
        return new self(
            enabled: $this->enabled,
            sourceName: $this->sourceName,
            monitorUrl: $monitorUrl,
            mailRecipients: $this->mailRecipients,
        );
    }

    public function withMailRecipients(EmailRecipientList $mailRecipients): self
    {
        return new self(
            enabled: $this->enabled,
            sourceName: $this->sourceName,
            monitorUrl: $this->monitorUrl,
            mailRecipients: $mailRecipients,
        );
    }
}
