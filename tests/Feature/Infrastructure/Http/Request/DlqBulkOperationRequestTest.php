<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Tests\Feature\Infrastructure\Http\Request;

use Illuminate\Support\Facades\Validator;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;
use Yammi\JobsMonitor\Infrastructure\Http\Request\DlqBulkOperationRequest;
use Yammi\JobsMonitor\Tests\TestCase;

final class DlqBulkOperationRequestTest extends TestCase
{
    public function test_single_valid_uuid_passes(): void
    {
        $validator = $this->validate([
            'ids' => ['550e8400-e29b-41d4-a716-446655440000'],
        ]);

        self::assertTrue($validator->passes());
    }

    public function test_multiple_valid_uuids_pass(): void
    {
        $validator = $this->validate([
            'ids' => [
                '550e8400-e29b-41d4-a716-446655440000',
                '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
                'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            ],
        ]);

        self::assertTrue($validator->passes());
    }

    public function test_missing_ids_fails(): void
    {
        $validator = $this->validate([]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('ids', $validator->errors()->toArray());
    }

    public function test_empty_ids_array_fails(): void
    {
        $validator = $this->validate(['ids' => []]);

        self::assertTrue($validator->fails());
    }

    public function test_non_uuid_string_fails(): void
    {
        $validator = $this->validate(['ids' => ['not-a-uuid']]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('ids.0', $validator->errors()->toArray());
    }

    public function test_integer_in_ids_fails(): void
    {
        $validator = $this->validate(['ids' => [123]]);

        self::assertTrue($validator->fails());
    }

    public function test_ids_exceeding_max_count_fails(): void
    {
        $ids = array_map(
            static fn (int $i): string => sprintf(
                '%08x-e29b-41d4-a716-%012x',
                $i,
                $i,
            ),
            range(1, DlqBulkOperationRequest::MAX_IDS + 1),
        );

        $validator = $this->validate(['ids' => $ids]);

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('ids', $validator->errors()->toArray());
    }

    public function test_ids_at_max_count_passes(): void
    {
        $ids = array_map(
            static fn (int $i): string => sprintf(
                '%08x-e29b-41d4-a716-%012x',
                $i,
                $i,
            ),
            range(1, DlqBulkOperationRequest::MAX_IDS),
        );

        $validator = $this->validate(['ids' => $ids]);

        self::assertTrue($validator->passes());
    }

    public function test_mixed_valid_and_invalid_uuids_fails(): void
    {
        $validator = $this->validate([
            'ids' => [
                '550e8400-e29b-41d4-a716-446655440000',
                'invalid',
            ],
        ]);

        self::assertTrue($validator->fails());
        self::assertArrayNotHasKey('ids.0', $validator->errors()->toArray());
        self::assertArrayHasKey('ids.1', $validator->errors()->toArray());
    }

    public function test_ids_not_array_fails(): void
    {
        $validator = $this->validate(['ids' => '550e8400-e29b-41d4-a716-446655440000']);

        self::assertTrue($validator->fails());
    }

    public function test_identifiers_accessor_returns_job_identifier_objects(): void
    {
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
        ];

        $request = DlqBulkOperationRequest::create('/test', 'POST', ['ids' => $uuids]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(\Illuminate\Routing\Redirector::class));
        $request->validateResolved();

        $identifiers = $request->identifiers();

        self::assertCount(2, $identifiers);
        self::assertContainsOnlyInstancesOf(JobIdentifier::class, $identifiers);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $identifiers[0]->value);
        self::assertSame('6ba7b810-9dad-11d1-80b4-00c04fd430c8', $identifiers[1]->value);
    }

    public function test_max_ids_constant_is_100(): void
    {
        self::assertSame(100, DlqBulkOperationRequest::MAX_IDS);
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new DlqBulkOperationRequest($data);
        $request->setContainer($this->app);

        return Validator::make($data, $request->rules());
    }
}
