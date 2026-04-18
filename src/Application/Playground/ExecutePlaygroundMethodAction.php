<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Application\Playground;

use Yammi\JobsMonitor\Application\Exception\InvalidPlaygroundArgument;
use Yammi\JobsMonitor\Application\Service\YammiJobsManageService;
use Yammi\JobsMonitor\Application\Service\YammiJobsQueryService;
use Yammi\JobsMonitor\Application\Service\YammiJobsSettingsService;

/**
 * Dispatches a catalogued facade method with coerced arguments and
 * returns the serialized result. Security guarantees:
 *
 *  - Method is resolved against the catalog; unknown keys throw
 *    {@see InvalidPlaygroundArgument}. Never are request strings used
 *    as method names on a service object.
 *  - The dispatch match below is exhaustive and deliberately listed —
 *    adding a method to the catalog without wiring it here will fail
 *    the covering feature test.
 *  - All arguments are coerced through {@see ArgumentCoercer} which
 *    validates shape before the call is made.
 */
final class ExecutePlaygroundMethodAction
{
    public function __construct(
        private readonly MethodCatalog $catalog,
        private readonly ArgumentCoercer $coercer,
        private readonly ResultSerializer $serializer,
        private readonly YammiJobsQueryService $query,
        private readonly YammiJobsManageService $manage,
        private readonly YammiJobsSettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $rawArgs
     */
    public function __invoke(string $methodKey, array $rawArgs): mixed
    {
        $method = $this->catalog->find($methodKey);
        if ($method === null) {
            throw new InvalidPlaygroundArgument(sprintf('Unknown method "%s".', $methodKey));
        }

        $args = [];
        foreach ($method->arguments as $argDef) {
            $args[$argDef->name] = $this->coercer->coerce($argDef, $rawArgs[$argDef->name] ?? null);
        }

        $result = $this->dispatch($method->key, $args);

        return $this->serializer->serialize($result);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function dispatch(string $key, array $args): mixed
    {
        return match ($key) {
            'YammiJobs::jobs' => $this->query->jobs($args['period'], $args['jobClass'], $args['status'], $args['page'], $args['perPage']),
            'YammiJobs::failed' => $this->query->failed($args['period'], $args['page'], $args['perPage']),
            'YammiJobs::attempts' => $this->query->attempts($args['uuid']),
            'YammiJobs::job' => $this->query->job($args['uuid'], $args['attempt']),
            'YammiJobs::dlq' => $this->query->dlq($args['page'], $args['perPage'], $args['maxTries']),
            'YammiJobs::dlqPayload' => $this->query->dlqPayload($args['uuid']),
            'YammiJobs::failureGroups' => $this->query->failureGroups($args['page'], $args['perPage']),
            'YammiJobs::failureGroup' => $this->query->failureGroup($args['fingerprint']),
            'YammiJobs::scheduled' => $this->query->scheduled([], $args['page'], $args['perPage']),
            'YammiJobs::scheduledStatusCounts' => $this->query->scheduledStatusCounts(),
            'YammiJobs::workers' => $this->query->workers($args['page'], $args['perPage']),
            'YammiJobs::countFailures' => $this->query->countFailures($args['period'], $args['minAttempt']),
            'YammiJobs::countPartialCompletions' => $this->query->countPartialCompletions($args['period']),
            'YammiJobs::countSilentSuccesses' => $this->query->countSilentSuccesses($args['period']),
            'YammiJobs::stats' => $this->query->stats($args['jobClass']),
            'YammiJobs::statsAll' => $this->query->statsAll($args['period']),
            'YammiJobs::statusCounts' => $this->query->statusCounts($args['period']),
            'YammiJobs::queueSize' => $this->query->queueSize($args['queue']),
            'YammiJobs::delayedSize' => $this->query->delayedSize($args['queue']),
            'YammiJobs::reservedSize' => $this->query->reservedSize($args['queue']),
            'YammiJobsManage::retryDlq' => $this->manage->retryDlq($args['uuid'], $args['payloadOverride']),
            'YammiJobsManage::deleteDlq' => $this->manage->deleteDlq($args['uuid']),
            'YammiJobsManage::retryDlqBulk' => $this->manage->retryDlqBulk($args['uuids']),
            'YammiJobsManage::deleteDlqBulk' => $this->manage->deleteDlqBulk($args['uuids']),
            'YammiJobsManage::retryFailureGroup' => $this->manage->retryFailureGroup($args['fingerprint']),
            'YammiJobsManage::deleteFailureGroup' => $this->manage->deleteFailureGroup($args['fingerprint']),
            'YammiJobsManage::refreshAnomalyBaselines' => $this->manage->refreshAnomalyBaselines($args['lookbackDays']),
            'YammiJobsSettings::general' => $this->settings->general(),
            'YammiJobsSettings::alerts' => $this->settings->alerts(),
            'YammiJobsSettings::rules' => $this->settings->rules(),
            'YammiJobsSettings::toggleAlerts' => $this->settings->toggleAlerts($args['enabled']),
            'YammiJobsSettings::addAlertRecipients' => $this->settings->addAlertRecipients($args['emails']),
            'YammiJobsSettings::removeAlertRecipient' => $this->settings->removeAlertRecipient($args['email']),
            'YammiJobsSettings::resetGeneral' => $this->settings->resetGeneral(),
            'YammiJobsSettings::toggleBuiltInRule' => $this->settings->toggleBuiltInRule($args['key'], $args['enabled']),
            'YammiJobsSettings::resetBuiltInRule' => $this->settings->resetBuiltInRule($args['key']),
            'YammiJobsSettings::deleteRule' => $this->settings->deleteRule($args['id']),
            default => throw new InvalidPlaygroundArgument(sprintf('Method "%s" is catalogued but not wired in the dispatcher.', $key)),
        };
    }
}
