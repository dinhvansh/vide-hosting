<?php

namespace App\Console\Commands;

use App\Services\ProductionPreflight;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('production:preflight')]
#[Description('Validate production security and operational configuration before migration')]
class ProductionPreflightCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProductionPreflight $preflight): int
    {
        $errors = $preflight->errors();
        if ($errors !== []) {
            $this->components->error('Production preflight failed.');
            foreach ($errors as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $this->components->info('Production preflight passed.');

        return self::SUCCESS;
    }
}
