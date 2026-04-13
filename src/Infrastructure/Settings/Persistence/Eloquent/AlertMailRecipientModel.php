<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * @internal Eloquent representation of a row in
 *           `jobs_monitor_alert_mail_recipients`.
 *
 * @property int $id
 * @property string $email
 */
final class AlertMailRecipientModel extends Model
{
    protected $table = 'jobs_monitor_alert_mail_recipients';

    protected $guarded = [];
}
