<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedProfile();
        $this->seedReports();
    }

    private function seedAdmin(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            $this->command?->warn('ADMIN_PASSWORD is not set in .env — admin user was NOT created.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            return; // never overwrite an existing password on re-seed
        }

        $user = new User(['name' => env('ADMIN_NAME', 'Admin'), 'email' => $email, 'password' => $password]);
        $user->is_admin = true;
        $user->save();
    }

    private function seedProfile(): void
    {
        if (Profile::count() > 0) {
            return;
        }
        $p = $this->json('profile.json');
        Profile::create([
            'name' => $p['name'],
            'role' => $p['role'] ?? null,
            'bio' => $p['bio'] ?? null,
            'location' => $p['location'] ?? null,
            'skills' => $p['skills'] ?? [],
            'ar' => $p['ar'] ?? null,
        ]);
    }

    private function seedReports(): void
    {
        if (Report::count() > 0) {
            return;
        }
        foreach ($this->json('reports.json') as $r) {
            $report = new Report([
                'platform' => $r['platform'] ?? null,
                'program_type' => $r['programType'] ?? null,
                'bounty' => $r['bounty'] ?? null,
                'asset' => $r['asset'] ?? null,
                'weakness' => $r['weakness'] ?? null,
                'title' => $r['title'],
                'summary' => $r['summary'],
                'severity' => $r['severity'] ?? 'Review',
                'steps_to_reproduce' => $r['stepsToReproduce'] ?? null,
                'request' => $r['request'] ?? null,
                'response' => $r['response'] ?? null,
                'supporting_references' => $r['references'] ?? null,
                'endpoint' => $r['endpoint'] ?? null,
                'impact' => $r['impact'] ?? null,
                'recommendation' => $r['recommendation'] ?? null,
                'ar' => $r['ar'] ?? null,
                'published' => true,
            ]);
            $report->id = $r['id']; // keep the ids the site already uses (/reports/2 ...)
            $report->save();
        }
    }

    private function json(string $file): array
    {
        return json_decode(file_get_contents(database_path('seeders/data/'.$file)), true, 512, JSON_THROW_ON_ERROR);
    }
}
