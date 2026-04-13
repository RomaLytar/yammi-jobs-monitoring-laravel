<?php

declare(strict_types=1);

namespace Yammi\JobsMonitor\Infrastructure\Http\Controller;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Yammi\JobsMonitor\Application\Action\AddAlertRecipientsAction;
use Yammi\JobsMonitor\Application\Action\GetAlertSettingsAction;
use Yammi\JobsMonitor\Application\Action\ListAlertRulesAction;
use Yammi\JobsMonitor\Application\Action\RemoveAlertRecipientAction;
use Yammi\JobsMonitor\Application\Action\ResetBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\ToggleAlertsAction;
use Yammi\JobsMonitor\Application\Action\ToggleBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Action\UpdateAlertScalarSettingsAction;
use Yammi\JobsMonitor\Application\Action\UpdateBuiltInRuleAction;
use Yammi\JobsMonitor\Application\Service\BuiltInRulesProvider;
use Yammi\JobsMonitor\Domain\Settings\Exception\InvalidEmailRecipient;
use Yammi\JobsMonitor\Domain\Settings\ValueObject\MonitorUrl;
use Yammi\JobsMonitor\Infrastructure\Http\Authorization\SettingsGate;
use Yammi\JobsMonitor\Infrastructure\Http\Request\AddAlertRecipientsRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Request\SaveBuiltInRuleRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Request\ToggleAlertsRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Request\ToggleBuiltInRuleRequest;
use Yammi\JobsMonitor\Infrastructure\Http\Request\UpdateAlertScalarsRequest;

/** @internal */
final class AlertSettingsController extends Controller
{
    public function index(
        SettingsGate $gate,
        GetAlertSettingsAction $get,
        ListAlertRulesAction $listRules,
    ): View {
        $gate->authorize();

        return view('jobs-monitor::settings.alerts.index', [
            'alerts' => $get(),
            'rulesOverview' => $listRules(),
            'editing' => $this->editingKey(),
        ]);
    }

    public function toggle(SettingsGate $gate, ToggleAlertsRequest $request, ToggleAlertsAction $toggle): RedirectResponse
    {
        $gate->authorize();
        $toggle($request->enabled());

        return redirect()
            ->route('jobs-monitor.settings.alerts')
            ->with('jobs_monitor_status', 'Alert toggle saved.');
    }

    public function update(
        SettingsGate $gate,
        UpdateAlertScalarsRequest $request,
        UpdateAlertScalarSettingsAction $update,
    ): RedirectResponse {
        $gate->authorize();

        $url = $request->monitorUrl();
        $update($request->sourceName(), $url === null ? null : new MonitorUrl($url));

        return redirect()
            ->route('jobs-monitor.settings.alerts')
            ->with('jobs_monitor_status', 'Alert settings saved.');
    }

    public function addRecipients(
        SettingsGate $gate,
        AddAlertRecipientsRequest $request,
        AddAlertRecipientsAction $add,
    ): RedirectResponse {
        $gate->authorize();

        try {
            $add($request->emails());
        } catch (InvalidEmailRecipient $e) {
            return redirect()
                ->route('jobs-monitor.settings.alerts')
                ->withErrors(['email' => $e->getMessage()]);
        }

        $count = count($request->emails());

        return redirect()
            ->route('jobs-monitor.settings.alerts')
            ->with('jobs_monitor_status', $count === 1
                ? 'Recipient added.'
                : sprintf('%d recipients added.', $count));
    }

    public function removeRecipient(
        SettingsGate $gate,
        string $email,
        RemoveAlertRecipientAction $remove,
    ): RedirectResponse {
        $gate->authorize();
        $remove(urldecode($email));

        return redirect()
            ->route('jobs-monitor.settings.alerts')
            ->with('jobs_monitor_status', 'Recipient removed.');
    }

    public function toggleBuiltIn(
        SettingsGate $gate,
        string $key,
        ToggleBuiltInRuleRequest $request,
        ToggleBuiltInRuleAction $toggle,
        BuiltInRulesProvider $provider,
    ): RedirectResponse {
        $gate->authorize();
        $this->assertBuiltInKey($key, $provider);

        $toggle($key, $request->enabled());

        return redirect()
            ->route('jobs-monitor.settings.alerts')
            ->with('jobs_monitor_status', 'Built-in rule updated.');
    }

    public function updateBuiltIn(
        SettingsGate $gate,
        string $key,
        SaveBuiltInRuleRequest $request,
        UpdateBuiltInRuleAction $update,
        BuiltInRulesProvider $provider,
    ): RedirectResponse {
        $gate->authorize();
        $this->assertBuiltInKey($key, $provider);

        $update($key, $request->buildAlertRule(), $request->enabled());

        return redirect()
            ->route('jobs-monitor.settings.alerts')
            ->with('jobs_monitor_status', 'Built-in rule saved.');
    }

    public function resetBuiltIn(
        SettingsGate $gate,
        string $key,
        ResetBuiltInRuleAction $reset,
        BuiltInRulesProvider $provider,
    ): RedirectResponse {
        $gate->authorize();
        $this->assertBuiltInKey($key, $provider);

        $reset($key);

        return redirect()
            ->route('jobs-monitor.settings.alerts')
            ->with('jobs_monitor_status', 'Built-in rule reset to shipped default.');
    }

    private function assertBuiltInKey(string $key, BuiltInRulesProvider $provider): void
    {
        if (! array_key_exists($key, $provider->catalog())) {
            abort(404);
        }
    }

    private function editingKey(): ?string
    {
        $value = request()->query('editing');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
