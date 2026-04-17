<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Playground;

/**
 * Argument types understood by the playground. Each type maps to:
 *  - a Form Request validation rule
 *  - a UI input control (text / number / select / textarea)
 *  - a coercion routine before the call is dispatched.
 */
enum ArgumentType: string
{
    case StringText = 'string';
    case Integer = 'int';
    case Boolean = 'bool';
    case NullableBoolean = 'nullable_bool';
    case Period = 'period';
    case Uuid = 'uuid';
    case UuidList = 'uuid_list';
    case Fingerprint = 'fingerprint';
    case FingerprintList = 'fingerprint_list';
    case JsonObject = 'json_object';
    case EmailList = 'email_list';
    case Email = 'email';
    case JobStatus = 'job_status';
}
