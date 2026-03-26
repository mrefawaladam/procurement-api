<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOldRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'procurement:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move requests older than 2 years to cold storage (archived_requests)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoffDate = now()->subYears(2);
        
        $this->info("Archiving records older than {$cutoffDate}...");

        DB::table('requests')
            ->where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['COMPLETED', 'REJECTED'])
            ->chunkById(5000, function ($requests) {
                $archiveData = $requests->map(fn($req) => [
                    'original_id' => $req->id,
                    'user_id' => $req->user_id,
                    'department_id' => $req->department_id,
                    'status' => $req->status,
                    'total_amount' => $req->total_amount,
                    'notes' => $req->notes,
                    'created_at' => $req->created_at,
                    'archived_at' => now(),
                ])->toArray();

                DB::table('archived_requests')->insert($archiveData);
                
                DB::table('requests')
                    ->whereIn('id', $requests->pluck('id'))
                    ->delete();
                    
                $this->info("Archived " . count($archiveData) . " records...");
            });

        $this->info("Archiving completed.");
    }
}
