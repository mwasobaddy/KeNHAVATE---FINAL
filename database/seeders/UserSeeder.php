<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Staff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * KeNHAVATE Innovation Portal - User Seeder
     * 
     * Creates test users for all 8 roles in the system:
     * 1. Developer - Kelvin Wanjohi (kelvinramsiel)
     * 2. Administrator - Fake admin user
     * 3. Board Member - Fake board members
     * 4. Manager - Fake managers
     * 5. Subject Matter Expert (SME) - Fake SMEs
     * 6. Challenge Reviewer - Fake challenge reviewers
     * 7. Idea Reviewer - Fake idea reviewers
     * 8. User - Regular users
     */
    public function run(): void
    {
        // Ensure roles exist before assigning them
        $this->ensureRolesExist();

        // Create Kelvin Wanjohi as Developer
        $this->createKelvinWanjohi();

        // Create fake users for each role
        $this->createAdministrators();
        $this->createBoardMembers();
        $this->createManagers();
        $this->createSMEs();
        $this->createChallengeReviewers();
        $this->createIdeaReviewers();
        $this->createRegularUsers();
        $this->createTestAccountStatusUsers();
        $this->createKenhaStaffUsers();

        $this->command->info('UserSeeder completed successfully!');
        $this->displayCreatedUsers();
    }

    /**
     * Ensure all required roles exist before creating users
     */
    private function ensureRolesExist(): void
    {
        $requiredRoles = [
            'developer',
            'administrator',
            'board_member',
            'manager',
            'sme',
            'challenge_reviewer',
            'idea_reviewer',
            'user'
        ];

        foreach ($requiredRoles as $roleName) {
            if (!Role::where('name', $roleName)->exists()) {
                $this->command->warn("Role '{$roleName}' does not exist. Please run RolesAndPermissionsSeeder first.");
            }
        }
    }

    /**
     * Create Kelvin Wanjohi as Developer
     */
    private function createKelvinWanjohi(): void
    {
        $kelvin = User::updateOrCreate(
            ['email' => 'kelvinramsiel@gmail.com'],
            [
                'first_name' => 'Kelvin',
                'last_name' => 'Wanjohi',
                'phone' => '+254712345678',
                'gender' => 'male',
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'account_status' => 'active',
                'terms_accepted' => true,
            ]
        );

        // Assign developer role
        $kelvin->assignRole('developer');

        // Create staff profile for Kelvin (KeNHA staff)
        Staff::updateOrCreate(
            ['user_id' => $kelvin->id],
            [
                'staff_number' => 'KNH001',
                'job_title' => 'Senior Systems Developer',
                'department' => 'Information Technology',
                'employment_type' => 'permanent',
                'employment_date' => now()->subYears(3),
                'work_station' => 'Nairobi Headquarters',
                'supervisor_name' => 'John Doe',
                'personal_email' => 'kelvin.wanjohi@gmail.com',
            ]
        );

        $this->command->info('✅ Created Kelvin Wanjohi as Developer');
    }

    /**
     * Create Administrator users
     */
    private function createAdministrators(): void
    {
        $admins = [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Mwangi',
                'email' => 'sarah.mwangi@kenha.co.ke',
                'staff_number' => 'KNH002',
                'job_title' => 'System Administrator',
                'department' => 'Information Technology',
            ],
            [
                'first_name' => 'James',
                'last_name' => 'Kiprotich',
                'email' => 'james.kiprotich@kenha.co.ke',
                'staff_number' => 'KNH003',
                'job_title' => 'Portal Administrator',
                'department' => 'Innovation & Strategy',
            ]
        ];

        foreach ($admins as $adminData) {
            $user = $this->createUserWithStaffProfile($adminData, 'administrator');
            $this->command->info("✅ Created Administrator: {$user->first_name} {$user->last_name}");
        }
    }

    /**
     * Create Board Member users
     */
    private function createBoardMembers(): void
    {
        $boardMembers = [
            [
                'first_name' => 'Dr. Margaret',
                'last_name' => 'Chepkoech',
                'email' => 'margaret.chepkoech@kenha.co.ke',
                'staff_number' => 'KNH004',
                'job_title' => 'Director of Strategy',
                'department' => 'Executive Office',
            ],
            [
                'first_name' => 'Eng. Peter',
                'last_name' => 'Njoroge',
                'email' => 'peter.njoroge@kenha.co.ke',
                'staff_number' => 'KNH005',
                'job_title' => 'Chief Executive Officer',
                'department' => 'Executive Office',
            ],
            [
                'first_name' => 'Prof. Grace',
                'last_name' => 'Wanjiku',
                'email' => 'grace.wanjiku@kenha.co.ke',
                'staff_number' => 'KNH006',
                'job_title' => 'Board Chairman',
                'department' => 'Board of Directors',
            ]
        ];

        foreach ($boardMembers as $memberData) {
            $user = $this->createUserWithStaffProfile($memberData, 'board_member');
            $this->command->info("✅ Created Board Member: {$user->first_name} {$user->last_name}");
        }
    }

    /**
     * Create Manager users
     */
    private function createManagers(): void
    {
        $managers = [
            [
                'first_name' => 'David',
                'last_name' => 'Ochieng',
                'email' => 'david.ochieng@kenha.co.ke',
                'staff_number' => 'KNH007',
                'job_title' => 'Innovation Manager',
                'department' => 'Innovation & Strategy',
            ],
            [
                'first_name' => 'Lucy',
                'last_name' => 'Mutua',
                'email' => 'lucy.mutua@kenha.co.ke',
                'staff_number' => 'KNH008',
                'job_title' => 'Project Manager',
                'department' => 'Project Management',
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Kamau',
                'email' => 'michael.kamau@kenha.co.ke',
                'staff_number' => 'KNH009',
                'job_title' => 'Operations Manager',
                'department' => 'Operations',
            ]
        ];

        foreach ($managers as $managerData) {
            $user = $this->createUserWithStaffProfile($managerData, 'manager');
            $this->command->info("✅ Created Manager: {$user->first_name} {$user->last_name}");
        }
    }

    /**
     * Create Subject Matter Expert (SME) users
     */
    private function createSMEs(): void
    {
        $smes = [
            [
                'first_name' => 'Eng. Catherine',
                'last_name' => 'Akinyi',
                'email' => 'catherine.akinyi@kenha.co.ke',
                'staff_number' => 'KNH010',
                'job_title' => 'Senior Road Engineer',
                'department' => 'Road Engineering',
            ],
            [
                'first_name' => 'Dr. Francis',
                'last_name' => 'Mburu',
                'email' => 'francis.mburu@kenha.co.ke',
                'staff_number' => 'KNH011',
                'job_title' => 'Materials Research Specialist',
                'department' => 'Research & Development',
            ],
            [
                'first_name' => 'Eng. Alice',
                'last_name' => 'Waweru',
                'email' => 'alice.waweru@kenha.co.ke',
                'staff_number' => 'KNH012',
                'job_title' => 'Environmental Engineer',
                'department' => 'Environmental & Social',
            ],
            [
                'first_name' => 'Joseph',
                'last_name' => 'Kiptoo',
                'email' => 'joseph.kiptoo@kenha.co.ke',
                'staff_number' => 'KNH013',
                'job_title' => 'ICT Systems Analyst',
                'department' => 'Information Technology',
            ]
        ];

        foreach ($smes as $smeData) {
            $user = $this->createUserWithStaffProfile($smeData, 'sme');
            $this->command->info("✅ Created SME: {$user->first_name} {$user->last_name}");
        }
    }

    /**
     * Create Challenge Reviewer users
     */
    private function createChallengeReviewers(): void
    {
        $reviewers = [
            [
                'first_name' => 'Robert',
                'last_name' => 'Macharia',
                'email' => 'robert.macharia@kenha.co.ke',
                'staff_number' => 'KNH014',
                'job_title' => 'Challenge Review Specialist',
                'department' => 'Innovation & Strategy',
            ],
            [
                'first_name' => 'Esther',
                'last_name' => 'Wanjala',
                'email' => 'esther.wanjala@kenha.co.ke',
                'staff_number' => 'KNH015',
                'job_title' => 'Innovation Review Officer',
                'department' => 'Innovation & Strategy',
            ]
        ];

        foreach ($reviewers as $reviewerData) {
            $user = $this->createUserWithStaffProfile($reviewerData, 'challenge_reviewer');
            $this->command->info("✅ Created Challenge Reviewer: {$user->first_name} {$user->last_name}");
        }
    }

    /**
     * Create Idea Reviewer users
     */
    private function createIdeaReviewers(): void
    {
        $reviewers = [
            [
                'first_name' => 'Patrick',
                'last_name' => 'Otieno',
                'email' => 'patrick.otieno@kenha.co.ke',
                'staff_number' => 'KNH016',
                'job_title' => 'Idea Review Specialist',
                'department' => 'Innovation & Strategy',
            ],
            [
                'first_name' => 'Mary',
                'last_name' => 'Nyong',
                'email' => 'mary.nyong@kenha.co.ke',
                'staff_number' => 'KNH017',
                'job_title' => 'Innovation Assessment Officer',
                'department' => 'Quality Assurance',
            ],
            [
                'first_name' => 'Samuel',
                'last_name' => 'Kiplagat',
                'email' => 'samuel.kiplagat@kenha.co.ke',
                'staff_number' => 'KNH018',
                'job_title' => 'Technical Review Officer',
                'department' => 'Technical Services',
            ]
        ];

        foreach ($reviewers as $reviewerData) {
            $user = $this->createUserWithStaffProfile($reviewerData, 'idea_reviewer');
            $this->command->info("✅ Created Idea Reviewer: {$user->first_name} {$user->last_name}");
        }
    }

    /**
     * Create regular User accounts (non-staff)
     */
    private function createRegularUsers(): void
    {
        $regularUsers = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@gmail.com',
                'phone' => '+254701234567',
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@yahoo.com',
                'phone' => '+254702345678',
            ],
            [
                'first_name' => 'Peter',
                'last_name' => 'Kimani',
                'email' => 'peter.kimani@outlook.com',
                'phone' => '+254703456789',
            ],
            [
                'first_name' => 'Grace',
                'last_name' => 'Muthoni',
                'email' => 'grace.muthoni@gmail.com',
                'phone' => '+254704567890',
            ],
            [
                'first_name' => 'Daniel',
                'last_name' => 'Kipchoge',
                'email' => 'daniel.kipchoge@hotmail.com',
                'phone' => '+254705678901',
            ]
        ];

        foreach ($regularUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'phone' => $userData['phone'],
                    'gender' => fake()->randomElement(['male', 'female']),
                    'email_verified_at' => now(),
                    'password' => Hash::make('password123'),
                    'account_status' => 'active',
                    'terms_accepted' => true,
                ]
            );

            $user->assignRole('user');
            $this->command->info("✅ Created Regular User: {$user->first_name} {$user->last_name}");
        }
    }

    /**
     * Create test users with different account statuses (banned/suspended) for testing appeal functionality
     */
    private function createTestAccountStatusUsers(): void
    {
        $testUsers = [
            [
                'first_name' => 'Banned',
                'last_name' => 'User',
                'email' => 'banned.user@gmail.com',
                'phone' => '+254706789012',
                'account_status' => 'banned',
            ],
            [
                'first_name' => 'Suspended',
                'last_name' => 'User', 
                'email' => 'suspended.user@gmail.com',
                'phone' => '+254707890123',
                'account_status' => 'suspended',
            ],
            [
                'first_name' => 'Temp',
                'last_name' => 'Suspended',
                'email' => 'temp.suspended@yahoo.com',
                'phone' => '+254708901234',
                'account_status' => 'suspended',
            ]
        ];

        foreach ($testUsers as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'phone' => $userData['phone'],
                    'gender' => fake()->randomElement(['male', 'female']),
                    'email_verified_at' => now(),
                    'password' => Hash::make('password123'),
                    'account_status' => $userData['account_status'],
                    'terms_accepted' => true,
                ]
            );

            $user->assignRole('user');
            $statusLabel = ucfirst($userData['account_status']);
            $this->command->info("⚠️  Created {$statusLabel} User: {$user->first_name} {$user->last_name} ({$user->email})");
        }
    }

    /**
     * Create additional KeNHA staff users using factory
     */
    private function createKenhaStaffUsers(): void
    {
        // Create 5 additional KeNHA staff members using factory
        $staffUsers = User::factory(5)->kenhaStaff()->create([
            'password' => Hash::make('password123'),
            'account_status' => 'active',
            'terms_accepted' => true,
        ]);

        foreach ($staffUsers as $user) {
            // Assign random roles (excluding developer)
            $roles = ['administrator', 'manager', 'sme', 'challenge_reviewer', 'idea_reviewer', 'user'];
            $randomRole = fake()->randomElement($roles);
            $user->assignRole($randomRole);

            // Create staff profile
            Staff::create([
                'user_id' => $user->id,
                'staff_number' => 'KNH' . str_pad(fake()->unique()->numberBetween(100, 999), 3, '0', STR_PAD_LEFT),
                'job_title' => fake()->jobTitle(),
                'department' => fake()->randomElement([
                    'Information Technology', 'Innovation & Strategy', 'Road Engineering',
                    'Project Management', 'Operations', 'Research & Development',
                    'Environmental & Social', 'Quality Assurance', 'Technical Services'
                ]),
                'employment_type' => fake()->randomElement(['permanent', 'contract', 'temporary']),
                'employment_date' => fake()->dateTimeBetween('-5 years', '-6 months'),
                'work_station' => fake()->randomElement(['Nairobi Headquarters', 'Mombasa Office', 'Kisumu Office', 'Nakuru Office']),
                'supervisor_name' => fake()->name(),
                'personal_email' => fake()->unique()->safeEmail(),
            ]);

            $this->command->info("✅ Created Factory KeNHA Staff: {$user->first_name} {$user->last_name} ({$randomRole})");
        }
    }

    /**
     * Helper method to create user with staff profile
     */
    private function createUserWithStaffProfile(array $userData, string $role): User
    {
        $user = User::updateOrCreate(
            ['email' => $userData['email']],
            [
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'phone' => '+254' . fake()->numberBetween(700000000, 799999999),
                'gender' => fake()->randomElement(['male', 'female']),
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'account_status' => 'active',
                'terms_accepted' => true,
            ]
        );

        // Assign role
        $user->assignRole($role);

        // Create staff profile
        Staff::updateOrCreate(
            ['user_id' => $user->id],
            [
                'staff_number' => $userData['staff_number'],
                'job_title' => $userData['job_title'],
                'department' => $userData['department'],
                'employment_type' => 'permanent',
                'employment_date' => fake()->dateTimeBetween('-10 years', '-1 year'),
                'work_station' => fake()->randomElement(['Nairobi Headquarters', 'Mombasa Office', 'Kisumu Office', 'Nakuru Office']),
                'supervisor_name' => fake()->name(),
                'personal_email' => strtolower($userData['first_name'] . '.' . $userData['last_name']) . '@gmail.com',
            ]
        );

        return $user;
    }

    /**
     * Display summary of created users
     */
    private function displayCreatedUsers(): void
    {
        $this->command->info("\n📊 User Creation Summary:");
        $this->command->info("================================");

        $roles = [
            'developer' => 'Developers',
            'administrator' => 'Administrators', 
            'board_member' => 'Board Members',
            'manager' => 'Managers',
            'sme' => 'Subject Matter Experts',
            'challenge_reviewer' => 'Challenge Reviewers',
            'idea_reviewer' => 'Idea Reviewers',
            'user' => 'Regular Users'
        ];

        foreach ($roles as $roleName => $roleDisplay) {
            $count = User::role($roleName)->count();
            $this->command->info("• {$roleDisplay}: {$count} users");
        }

        $totalUsers = User::count();
        $totalStaff = Staff::count();
        $activeUsers = User::where('account_status', 'active')->count();
        $bannedUsers = User::where('account_status', 'banned')->count();
        $suspendedUsers = User::where('account_status', 'suspended')->count();
        
        $this->command->info("--------------------------------");
        $this->command->info("📈 Total Users: {$totalUsers}");
        $this->command->info("🏢 KeNHA Staff: {$totalStaff}");
        $this->command->info("✅ Active Users: {$activeUsers}");
        if ($bannedUsers > 0) {
            $this->command->info("🚫 Banned Users: {$bannedUsers}");
        }
        if ($suspendedUsers > 0) {
            $this->command->info("⏸️  Suspended Users: {$suspendedUsers}");
        }
        $this->command->info("================================");
        $this->command->info("🔑 Default Password: password123");
        $this->command->info("✅ Most users are active and email verified");
        if ($bannedUsers > 0 || $suspendedUsers > 0) {
            $this->command->info("⚠️  Test appeal functionality with banned/suspended users");
        }
    }
}
