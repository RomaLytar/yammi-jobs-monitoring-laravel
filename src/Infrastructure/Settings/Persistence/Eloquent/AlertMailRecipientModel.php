<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobsMonitorModel;

/**
 * @internal Eloquent representation of a row in
 *           `jobs_monitor_alert_mail_recipients`.
 *
 * @property int $id
 * @property string $email
 */
final class AlertMailRecipientModel extends JobsMonitorModel
{
    protected $table = 'jobs_monitor_alert_mail_recipients';

    /** @var list<string> */
    protected $fillable = [
        'email',
    ];
}
