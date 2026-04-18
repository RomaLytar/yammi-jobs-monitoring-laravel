<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Unit\Application\Playground;

use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;
use Yammi\JobsMonitor\Application\Action\AddAlertRecipientsAction;
use Yammi\JobsMonitor\Application\Action\BulkDeleteDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\BulkRetryDeadLetterAction;
use Yammi\JobsMonitor\Application\Action\DeleteManagedAlertRuleAction;
use Yammi\JobsMonitor\Application\Action\GetAlertSettingsAction;
use Yammi\JobsMonitor\Application\Action\GetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\ListAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\RefreshDurationBaselinesAction;
use Yammi\JobsMonitor\Application\Action\RemoveAlertRecipientAction;
use Yammi\JobsMonitor\Application\Action\ResetBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\ResetGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Action\RetryDeadLetterJobAction;
use Yammi\JobsMonitor\Application\Action\SaveManagedAlertRuleAction;
use Yammi\JobsMonitor\Application\Action\ToggleAlertsAction;
use Yammi\JobsMonitor\Application\Action\ToggleBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\UpdateAlertScalarSettingsAction;
use Yammi\JobsMonitor\Application\Action\UpdateBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\UpdateGeneralSettingsAction;
use Yammi\JobsMonitor\Application\Exception\InvalidPlaygroundArgument;
use Yammi\JobsMonitor\Application\Playground\ArgumentCoercer;
use Yammi\JobsMonitor\Application\Playground\ExecutePlaygroundMethodAction;
use Yammi\JobsMonitor\Application\Playground\MethodCatalog;
use Yammi\JobsMonitor\Application\Playground\ResultSerializer;
use Yammi\JobsMonitor\Application\Service\AlertRuleFactory;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Application\Service\PayloadRedactor;
use Yammi\JobsMonitor\Application\Service\PercentileCalculator;
use Yammi\JobsMonitor\Application\Service\SettingRegistry;
use Yammi\JobsMonitor\Application\Service\YammiJobsManageService;
use Yammi\JobsMonitor\Application\Service\YammiJobsQueryService;
use Yammi\JobsMonitor\Application\Service\YammiJobsSettingsService;
use Yammi\JobsMonitor\Domain\Job\Entity\JobRecord;
use Yammi\JobsMonitor\Domain\Job\Enum\FailureCategory;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Domain\Job\ValueObject\QueueName;
use Yammi\JobsMonitor\Infrastructure\Metrics\NullMetricsDriver;
use Yammi\JobsMonitor\Tests\Support\ArrayConfigReader;
use Yammi\JobsMonitor\Tests\Support\InMemoryAlertSettingsRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryBuiltInRuleStateRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryDurationBaselineRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryFailureGroupRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryGeneralSettingRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryJobRecordRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryManagedAlertRuleRepository;
use Yammi\JobsMonitor\Tests\Support\InMemoryWorkerRepository;
use Yammi\JobsMonitor\Tests\Support\RecordingQueueDispatcher;
use Yammi\JobsMonitor\Tests\Support\SequentialUuidGenerator;

final class ExecutePlaygroundMethodActionTest extends TestCase
{
    private InMemoryJobRecordRepository $jobs;

    private ExecutePlaygroundMethodAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobs = new InMemoryJobRecordRepository;
        $groups = new InMemoryFailureGroupRepository;
        $scheduled = new FakeScheduledTaskRunRepositoryForPlayground;
        $workers = new InMemoryWorkerRepository;

        $query = new YammiJobsQueryService($this->jobs, $groups, $scheduled, $workers, new NullMetricsDriver);

        $retry = new RetryDeadLetterJobAction($this->jobs, new RecordingQueueDispatcher, new SequentialUuidGenerator);
        $manage = new YammiJobsManageService(
            $this->jobs,
            $groups,
            $retry,
            new BulkRetryDeadLetterAction($retry),
            new BulkDeleteDeadLetterAction($this->jobs),
            new RefreshDurationBaselinesAction(new InMemoryDurationBaselineRepository, new PercentileCalculator),
        );

        $this->action = new ExecutePlaygroundMethodAction(
            new MethodCatalog,
            new ArgumentCoercer,
            new ResultSerializer(new PayloadRedactor),
            $query,
            $manage,
            $this->buildSettingsService(),
        );
    }

    private function buildSettingsService(): YammiJobsSettingsService
    {
        $general = new InMemoryGeneralSettingRepository;
        $alerts = new InMemoryAlertSettingsRepository;
        $rules = new InMemoryManagedAlertRuleRepository;
        $state = new InMemoryBuiltInRuleStateRepository;
        $registry = new SettingRegistry;
        $config = new ArrayConfigReader;

        return new YammiJobsSettingsService(
            getGeneral: new GetGeneralSettingsAction($general, $registry, $config),
            updateGeneral: new UpdateGeneralSettingsAction($general, $registry),
            resetGeneral: new ResetGeneralSettingsAction($general, $registry),
            getAlerts: new GetAlertSettingsAction($alerts, null, null, null, null, null, []),
            toggleAlerts: new ToggleAlertsAction($alerts),
            updateAlertScalars: new UpdateAlertScalarSettingsAction($alerts),
            addRecipients: new AddAlertRecipientsAction($alerts),
            removeRecipient: new RemoveAlertRecipientAction($alerts),
            listRules: new ListAlertRulesAction(new BuiltInRulesProvider(new AlertRuleFactory), $state, $rules),
            saveRule: new SaveManagedAlertRuleAction($rules),
            deleteRule: new DeleteManagedAlertRuleAction($rules),
            toggleBuiltIn: new ToggleBuiltInRuleAction($state, $rules),
            updateBuiltIn: new UpdateBuiltInRuleAction($rules),
            resetBuiltIn: new ResetBuiltInRuleAction($rules, $state),
            rules: $rules,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_unknown_method_is_rejected(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        ($this->action)('YammiJobs::nuke_world', []);
    }

    public function test_cannot_call_service_method_not_in_catalog(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        // addAlertRecipients is catalogued, but verify hypothetical hidden methods cannot leak.
        ($this->action)('YammiJobsQueryService::jobs', []);
    }

    public function test_happy_path_returns_serialized_paged_result(): void
    {
        $this->jobs->save(new JobRecord(
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            Attempt::first(),
            'App\\Jobs\\X',
            'redis',
            new QueueName('default'),
            new DateTimeImmutable,
        ));

        $result = ($this->action)('YammiJobs::jobs', ['period' => 'all']);

        self::assertIsArray($result);
        self::assertSame(1, $result['total']);
        self::assertSame('550e8400-e29b-41d4-a716-446655440001', $result['items'][0]['uuid']);
    }

    public function test_invalid_uuid_throws_typed_exception(): void
    {
        $this->expectException(InvalidPlaygroundArgument::class);

        ($this->action)('YammiJobs::attempts', ['uuid' => 'not-a-uuid']);
    }

    public function test_bad_period_bubbles_invalid_period(): void
    {
        $this->expectException(\Yammi\JobsMonitor\Domain\Shared\Exception\InvalidPeriod::class);

        ($this->action)('YammiJobs::failed', ['period' => '5y']);
    }

    public function test_bulk_retry_returns_bulk_result_shape(): void
    {
        $record = new JobRecord(
            new JobIdentifier('550e8400-e29b-41d4-a716-446655440001'),
            Attempt::first(),
            'App\\Jobs\\X',
            'redis',
            new QueueName('default'),
            new DateTimeImmutable,
        );
        $record->setPayload(['uuid' => '550e8400-e29b-41d4-a716-446655440001', 'job' => 'App\\Jobs\\X', 'data' => []]);
        $record->markAsFailed(new DateTimeImmutable('+1 second'), 'boom', FailureCategory::Permanent);
        $this->jobs->save($record);

        $result = ($this->action)('YammiJobsManage::retryDlqBulk', [
            'uuids' => '550e8400-e29b-41d4-a716-446655440001',
        ]);

        self::assertSame(1, $result['succeeded']);
        self::assertSame(0, $result['failed']);
    }
}

final class FakeScheduledTaskRunRepositoryForPlayground implements \Yammi\JobsMonitor\Domain\Scheduler\Repository\ScheduledTaskRunRepository
{
    public function save(\Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun $run): void {}

    public function findRunning(string $mutex, DateTimeImmutable $startedAt): ?\Yammi\JobsMonitor\Domain\Scheduler\Entity\ScheduledTaskRun
    {
        return null;
    }

    public function findStuckRunning(DateTimeImmutable $olderThan): iterable
    {
        return [];
    }

    public function countFailedSince(DateTimeImmutable $since): int
    {
        return 0;
    }

    public function countLateSince(DateTimeImmutable $since): int
    {
        return 0;
    }

    public function latestRunPerMutex(): array
    {
        return [];
    }

    public function findPaginated(int $perPage, int $page, array $filters): array
    {
        return ['rows' => [], 'total' => 0];
    }

    public function statusCounts(): array
    {
        return [];
    }
}
