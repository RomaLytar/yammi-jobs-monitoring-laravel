<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Settings\Persistence\Eloquent;

<<<<<<< HEAD
use Yammi\JobsMonitor\Infrastructure\Persistence\Eloquent\JobsMonitorModel;
=======
use Illuminate\Database\Eloquent\Model;
>>>>>>> origin/main

/**
 * @internal Eloquent representation of a row in
 *           `jobs_monitor_alert_mail_recipients`.
 *
 * @property int $id
 * @property string $email
 */
<<<<<<< HEAD
final class AlertMailRecipientModel extends JobsMonitorModel
{
    protected $table = 'jobs_monitor_alert_mail_recipients';

    /** @var list<string> */
    protected $fillable = [
        'email',
    ];
=======
final class AlertMailRecipientModel extends Model
{
    protected $table = 'jobs_monitor_alert_mail_recipients';

    protected $guarded = [];
>>>>>>> origin/main
}
