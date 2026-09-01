<?php

namespace App\Services;

use App\Contracts\DeploymentProvider;
use App\Models\Application;
use App\Models\Database;
use Illuminate\Support\Str;

class DatabaseService
{
    public function __construct(private DeploymentProvider $provider) {}

    /** @return array{database: Database, password: string} */
    public function create(Application $application, string $type): array
    {
        $suffix = Str::lower(Str::random(8));
        $databaseName = 'vive_'.$suffix;
        $databaseUser = 'u_'.$suffix;
        $password = Str::password(32, symbols: false);
        $provider = $this->provider->createDatabase($application, $type, $databaseName, $databaseUser, $password);
        $database = $application->databases()->create([
            'type' => $type, 'database_name' => $databaseName, 'database_user' => $databaseUser,
            'encrypted_password' => $password, 'host' => $provider['host'], 'port' => $provider['port'],
            'provider_database_id' => $provider['provider_database_id'], 'status' => 'RUNNING',
        ]);

        $databaseVariables = [
            'DB_HOST' => $provider['host'], 'DB_PORT' => (string) $provider['port'], 'DB_DATABASE' => $databaseName,
            'DB_USERNAME' => $databaseUser, 'DB_PASSWORD' => $password,
            'DATABASE_URL' => $this->connectionUrl($type, $provider['host'], $provider['port'], $databaseName, $databaseUser, $password),
        ];
        $variables = $application->environmentVariables()->get()
            ->mapWithKeys(fn ($variable): array => [$variable->key => $variable->encrypted_value])
            ->all();
        $variables = array_merge($variables, $databaseVariables);
        $this->provider->setEnvironmentVariables($application, $variables);
        foreach ($databaseVariables as $key => $value) {
            $application->environmentVariables()->updateOrCreate(
                ['key' => $key],
                ['encrypted_value' => $value, 'is_secret' => in_array($key, ['DB_USERNAME', 'DB_PASSWORD', 'DATABASE_URL'], true)],
            );
        }

        return ['database' => $database, 'password' => $password];
    }

    public function delete(Application $application, Database $database): void
    {
        $this->provider->deleteDatabase($application, $database->provider_database_id);
        $database->delete();
    }

    private function connectionUrl(string $type, string $host, int $port, string $databaseName, string $databaseUser, string $password): string
    {
        $scheme = $type === 'POSTGRESQL' ? 'postgresql' : 'mysql';

        return $scheme.'://'.rawurlencode($databaseUser).':'.rawurlencode($password).'@'.$host.':'.$port.'/'.rawurlencode($databaseName);
    }
}
