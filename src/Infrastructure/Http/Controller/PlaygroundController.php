<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Throwable;
use Yammi\JobsMonitor\Application\Exception\InvalidPagination;
use Yammi\JobsMonitor\Application\Exception\InvalidPlaygroundArgument;
use Yammi\JobsMonitor\Application\Playground\ExecutePlaygroundMethodAction;
use Yammi\JobsMonitor\Application\Playground\MethodCatalog;
use Yammi\JobsMonitor\Domain\Exception\DomainException;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Http\Request\PlaygroundExecuteRequest;

/**
 * Settings → Facade playground: render method catalog + execute one
 * whitelisted call against a backing service.
 *
 * @internal
 */
final class PlaygroundController extends Controller
{
    public function __construct(
        private readonly MethodCatalog $catalog,
        private readonly ConfigRepository $config,
    ) {}

    public function index(SettingsGate $gate): View
    {
        $gate->authorize();

        return view('jobs-monitor::settings.playground.index', [
            'grouped' => $this->catalog->grouped(),
        ]);
    }

    public function execute(
        SettingsGate $gate,
        PlaygroundExecuteRequest $request,
        ExecutePlaygroundMethodAction $action,
    ): JsonResponse {
        $gate->authorize();

        $method = $this->catalog->find($request->methodKey());
        if ($method === null) {
            return new JsonResponse(['error' => 'Unknown method.'], 404);
        }

        if ($method->destructive) {
            $this->authorizeDestructive($method->method);
        }

        try {
            $result = $action($method->key, $request->args());
        } catch (InvalidPlaygroundArgument|InvalidPagination|DomainException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'error_class' => (new \ReflectionClass($e))->getShortName(),
            ], 422);
        } catch (Throwable $e) {
            return new JsonResponse([
                'error' => 'Execution failed.',
                'detail' => $e->getMessage(),
                'error_class' => (new \ReflectionClass($e))->getShortName(),
            ], 500);
        }

        return new JsonResponse([
            'method' => $method->key,
            'result' => $result,
        ]);
    }

    private function authorizeDestructive(string $action): void
    {
        /** @var string|null $ability */
        $ability = $this->config->get('jobs-monitor.playground.authorization')
            ?? $this->config->get('jobs-monitor.dlq.authorization');

        if ($ability === null) {
            if (! auth()->check()) {
                abort(403);
            }

            return;
        }

        if (! Gate::check($ability, $action)) {
            abort(403);
        }
    }
}
