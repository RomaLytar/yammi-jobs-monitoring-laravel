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
            'facadeInfo' => $this->catalog->facadeInfo(),
        ]);
    }

    public function execute(
        SettingsGate $gate,
        PlaygroundExecuteRequest $request,
        ExecutePlaygroundMethodAction $action,
    ): JsonResponse {
        try {
            $gate->authorize();

            $method = $this->catalog->find($request->methodKey());
            if ($method === null) {
                return $this->error('Unknown method.', 404, 'UnknownMethod');
            }

            if ($method->destructive && ! $this->isAllowedDestructive($method->method)) {
                return $this->error(
                    'Destructive method not allowed in this environment. '
                    .'Log in, or configure jobs-monitor.playground.authorization with an ability.',
                    403,
                    'PlaygroundForbidden',
                );
            }

            $result = $action($method->key, $request->args());

            return new JsonResponse([
                'method' => $method->key,
                'result' => $result,
            ]);
        } catch (InvalidPlaygroundArgument|InvalidPagination|DomainException $e) {
            return $this->error($e->getMessage(), 422, (new \ReflectionClass($e))->getShortName());
        } catch (Throwable $e) {
            return $this->error('Execution failed: '.$e->getMessage(), 500, (new \ReflectionClass($e))->getShortName());
        }
    }

    private function error(string $message, int $status, string $errorClass): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
            'error_class' => $errorClass,
        ], $status);
    }

    private function isAllowedDestructive(string $action): bool
    {
        /** @var string|null $ability */
        $ability = $this->config->get('jobs-monitor.playground.authorization')
            ?? $this->config->get('jobs-monitor.dlq.authorization');

        if ($ability === null) {
            // No explicit ability configured — fall back to authenticated user check.
            // Hosts that mount /settings behind their own middleware and want the
            // playground to mutate without login should set the ability to a
            // permissive Gate definition.
            return auth()->check();
        }

        return Gate::check($ability, $action);
    }
}
