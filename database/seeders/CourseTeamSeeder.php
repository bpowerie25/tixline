<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class CourseTeamSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = Tenant::first()?->id;

        $teams = [
            ['name' => 'General Support', 'color' => '#3b82f6'],
            ['name' => 'AI for Business', 'color' => '#8b5cf6'],
            ['name' => 'Blockchain Technologies', 'color' => '#ec4899'],
            ['name' => 'Business Programming', 'color' => '#f59e0b'],
            ['name' => 'Cybersecurity for Business', 'color' => '#ef4444'],
            ['name' => 'Cloud Computing for Business', 'color' => '#06b6d4'],
            ['name' => 'Data Governance and Ethics', 'color' => '#14b8a6'],
            ['name' => 'Data Science for Business', 'color' => '#6366f1'],
            ['name' => 'Digital Transformation', 'color' => '#f97316'],
            ['name' => 'Generative AI', 'color' => '#a855f7'],
            ['name' => 'Innovation', 'color' => '#22c55e'],
            ['name' => 'Internet of Things', 'color' => '#0ea5e9'],
            ['name' => 'Quantum Computing', 'color' => '#e11d48'],
            ['name' => 'Risk and Change Management', 'color' => '#78716c'],
            ['name' => 'Admissions', 'color' => '#2563eb'],
            ['name' => 'QA', 'color' => '#84cc16'],
            ['name' => 'Technical', 'color' => '#10b981'],
        ];

        foreach ($teams as $team) {
            Team::firstOrCreate(
                ['name' => $team['name']],
                ['color' => $team['color']]
            );
        }

        // Agents
        $agents = [
            ['name' => 'Marika Mascitti', 'email' => 'marika.mascitti@unibo.it', 'role' => 'agent'],
            ['name' => 'Roberto Henriques', 'email' => 'roberto@novaims.unl.pt', 'role' => 'agent'],
            ['name' => 'José AméricoRio', 'email' => 'jrio@novaims.unl.pt', 'role' => 'agent'],
            ['name' => 'Yuriy Perezhohin', 'email' => 'yperezhohin@novaims.unl.pt', 'role' => 'agent'],
            ['name' => 'JoãoSilva Martins', 'email' => 'jmartins@novaims.unl.pt', 'role' => 'agent'],
            ['name' => 'Gurjot Singh', 'email' => 'gurjot.singh@liu.se', 'role' => 'agent'],
            ['name' => 'Katerina Linden', 'email' => 'katerina.linden@liu.se', 'role' => 'agent'],
            ['name' => 'Mark Cudden', 'email' => 'Mark.Cudden@ncirl.ie', 'role' => 'agent'],
            ['name' => 'Michael Bradford', 'email' => 'michael.bradford@ncirl.ie', 'role' => 'agent'],
            ['name' => 'Radu Ciobanu', 'email' => 'radu.ciobanu@upb.ro', 'role' => 'agent'],
            ['name' => 'Cătălin Negru', 'email' => 'Catalin.Negru@ncirl.ie', 'role' => 'agent'],
            ['name' => 'Bogdan Mocanu', 'email' => 'Bogdan.Mocanu@ncirl.ie', 'role' => 'agent'],
            ['name' => 'Grace Herbert', 'email' => 'Grace.Herbert@ncirl.ie', 'role' => 'agent'],
            ['name' => 'Tomas Herink', 'email' => 'tomas@matrixinternet.ie', 'role' => 'admin'],
        ];

        foreach ($agents as $agent) {
            User::firstOrCreate(
                ['email' => $agent['email']],
                [
                    'name' => $agent['name'],
                    'password' => 'ChangeMeNow2026!',
                    'role' => $agent['role'],
                    'tenant_id' => $tenantId,
                ]
            );
        }
    }
}
