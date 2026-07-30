<?php

namespace App\Services;

use App\Services\Sms\SmsGatewayStatusService;
use Illuminate\Support\Facades\DB;

class MonitoringService
{
    public function __construct(private readonly SmsGatewayStatusService $smsGatewayStatus)
    {
    }

    public function getStatus(): array
    {
        return [
            'queues' => $this->queuesStatus(),
            'ws'     => $this->wsStatus(),
            'fcm'    => $this->fcmStatus(),
            'sms'    => $this->smsGatewayStatus->resolve(),
        ];
    }

    private function queuesStatus(): array
    {
        $checkedAt = now()->toIso8601String();

        try {
            $repository = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class);
            $masters    = collect($repository->all());
            $ok         = $masters->contains(fn ($master) => $master->status === 'running');
            $worker     = $masters->first()?->name;
        } catch (\Throwable) {
            $ok     = false;
            $worker = null;
        }

        return [
            'ok'         => $ok,
            'pending'    => DB::table('jobs')->count(),
            'failed'     => DB::table('failed_jobs')->count(),
            'worker'     => $worker,
            'checked_at' => $checkedAt,
        ];
    }

    private function wsStatus(): array
    {
        $host = $this->wsHost();
        $port = (int) config('reverb.servers.reverb.port', 8080);

        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        $ok     = (bool) $socket;
        if ($socket) {
            fclose($socket);
        }

        return [
            'ok'         => $ok,
            'host'       => $host,
            'port'       => $port,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Адрес для проверки доступности Reverb.
     * config('reverb.servers.reverb.host') — это адрес прослушивания (0.0.0.0),
     * подключаться по нему нельзя, поэтому подменяем на явный или на hostname.
     */
    private function wsHost(): string
    {
        if (filled($health = config('services.reverb.health_host'))) {
            return (string) $health;
        }

        $host = (string) config('reverb.servers.reverb.host', '127.0.0.1');

        if (in_array($host, ['0.0.0.0', '::', ''], true)) {
            return (string) (config('reverb.servers.reverb.hostname') ?: '127.0.0.1');
        }

        return $host;
    }

    private function fcmStatus(): array
    {
        $checkedAt       = now()->toIso8601String();
        $credentialsPath = config('firebase.projects.app.credentials');

        $configured = false;
        $projectId  = null;

        if (filled($credentialsPath)) {
            $path = str_starts_with($credentialsPath, '/') ? $credentialsPath : base_path($credentialsPath);

            if (is_file($path)) {
                $decoded    = json_decode((string) file_get_contents($path), true);
                $configured = isset($decoded['project_id'], $decoded['private_key'], $decoded['client_email']);
                $projectId  = $decoded['project_id'] ?? null;
            }
        }

        $ok = false;
        if ($configured) {
            try {
                app(\Kreait\Firebase\Contract\Messaging::class);
                $ok = true;
            } catch (\Throwable) {
                $ok = false;
            }
        }

        return [
            'ok'         => $ok,
            'configured' => $configured,
            'project_id' => $projectId ?? config('services.fcm.project_id'),
            'tokens'     => DB::table('fcm_tokens')->count(),
            'checked_at' => $checkedAt,
        ];
    }
}
