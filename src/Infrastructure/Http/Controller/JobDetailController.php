<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Domain\Job\Repository\JobRecordRepository;
use Yammi\JobsMonitor\Domain\Job\ValueObject\Attempt;
use Yammi\JobsMonitor\Domain\Job\ValueObject\JobIdentifier;

/** @internal */
final class JobDetailController extends Controller
{
    public function __invoke(
        string $uuid,
        int $attempt,
        JobRecordRepository $repository,
    ): View|Response {
        $record = $repository->findByIdentifierAndAttempt(
            new JobIdentifier($uuid),
            new Attempt($attempt),
        );

        if ($record === null) {
            abort(404);
        }

        return view('jobs-monitor::detail', ['record' => $record]);
    }
}
